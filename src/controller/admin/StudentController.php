<?php

namespace controller\admin;

use lib\Mangoose\Model;
use lib\Router\classes\Request;
use lib\Router\classes\Response;
use Ramsey\Uuid\Uuid;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


class StudentController
{

    private const BASE_URL = "views/admin/student";
    private const BASE_MODEL = "STUDENT";
    private const ROLES = ["admin", "user"];

    private static function getBaseModel()
    {
        return new Model(self::BASE_MODEL);
    }



    // ================================= Renderers ======================================================


    /**
     * Display the screen of the user
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function renderScreen(Request $req, Response $res)
    {
        try {
            $studentModel = self::getBaseModel();
            $departmentModel = new Model("DEPARTMENT");
            $courseModel = new Model("COURSE");


            $students = $studentModel->find();
            $department_list = $departmentModel->find();
            $course_list = $courseModel->find();

            $transformed_students = [];
            foreach ($students as $details) {
                $accountResult = (new Model("USERS"))->findOne(["ID" => $details["USER_ID"]]);
                $courseResult = (new Model("COURSE"))->findOne(["ID" => $details["COURSE_ID"]], ["select" => "NAME"]);
                $departmentResult = (new Model("DEPARTMENT"))->findOne(["ID" => $details["DEPARTMENT_ID"]], ["select" => "NAME"]);


                $fullName = $accountResult ? $accountResult["FIRST_NAME"] . " " . $accountResult["LAST_NAME"] : "Unknown";

                $transformed_students[] = [
                    ...$details,
                    "FULL_NAME" => $fullName,
                    "COURSE" => $courseResult["NAME"],
                    "DEPARTMENT" => $departmentResult["NAME"],
                ];
            }

            $res->status(200)->render(self::BASE_URL . "/screen.view.php", ["department_list" => $department_list, "course_list" => $course_list, "students" => $transformed_students]);

        } catch (\Exception $e) {
            $res->status(500)->json(["error" => "Failed to fetch Students: " . $e->getMessage()]);
        }
    }


    /**
     * Display the create page
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function renderCreatePage(Request $req, Response $res)
    {
        $departmentModel = new Model("DEPARTMENT");
        $courseModel = new Model("COURSE");
        $courseCredentials = $courseModel->find([]);
        $departmentCredentials = $departmentModel->find([]);

        $res->status(200)->render(self::BASE_URL . "/pages/create.page.php", ["departmentList" => $departmentCredentials, "courseList" => $courseCredentials, "roles" => self::ROLES]);
    }


    /**
     * Display the Update Page
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function renderUpdatePage(Request $req, Response $res)
    {
        try {
            $studentID = $req->query["id"];
            $studentModel = new Model("STUDENT");
            $accountModel = new Model("USERS");
            $courseModel = new Model("COURSE");
            $departmentModel = new Model("DEPARTMENT");

            $students = $studentModel->findOne(["ID" => $studentID]);
            $department_list = $departmentModel->find([]);
            $course_list = $courseModel->find([]);

            $account_credentials = $accountModel->findOne(
                ["ID" => $students["USER_ID"]],

            );

            $transformed_students = [
                ...$account_credentials,
                ...$students
            ];


            $res->status(200)->render(
                self::BASE_URL . "/pages/update.page.php",
                [
                    "UID" => $studentID,
                    "department_list" => $department_list,
                    "course_list" => $course_list,
                    "student_details" => $transformed_students,
                    "roles" => self::ROLES
                ]
            );



        } catch (\Exception $e) {
            $res->status(500)->json(["error" => "Failed to fetch users: " . $e->getMessage()]);
        }
    }


    // ================================= Actions ======================================================

    /**
     * Create users Handler
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function createStudent(Request $req, Response $res)
    {
        $credentials = $req->body;
        $UID = Uuid::uuid4()->toString();

        $studentModel = new Model("STUDENT");
        $accountModel = new Model("USERS");


        // check if the email exist

        $existingEmail = $accountModel->findOne(["EMAIL" => $credentials["EMAIL"],]);

        if ($existingEmail) {
            $res->status(400)->redirect("/student/create", ["error" => "Email  Already exist"]);
        }

        $existingPhoneNumber = $accountModel->findOne(["PHONE_NUMBER" => $credentials["PHONE_NUMBER"],]);

        if ($existingPhoneNumber) {
            $res->status(400)->redirect("/student/create", ["error" => "Phone number  Already exist"]);
        }

        $existingStudent = $studentModel->findOne(["STUDENT_ID" => $credentials["STUDENT_ID"]]);

        if ($existingStudent) {
            $res->status(400)->redirect("/student/create", ["error" => "Student Already exist"]);
        }

        $hashed_password = password_hash($credentials["PASSWORD"], PASSWORD_BCRYPT, ["cost" => 10]);

        // create the account
        $accountCredentials = $accountModel->createOne([
            "ID" => $UID,
            "FIRST_NAME" => $credentials["FIRST_NAME"],
            "LAST_NAME" => $credentials["LAST_NAME"],
            "PHONE_NUMBER" => $credentials["PHONE_NUMBER"],
            "GENDER" => $credentials["GENDER"],
            "ADDRESS" => $credentials["ADDRESS"],
            "EMAIL" => $credentials["EMAIL"],
            "PASSWORD" => $hashed_password,
            "ROLE" => "student"
        ]);

        if (!$accountCredentials) {
            $res->status(400)->redirect("/student/create", ["error" => "Create Student Account Failed"]);
        }

        $studentCredentials = $studentModel->createOne([
            "ID" => Uuid::uuid4()->toString(),
            "USER_ID" => $UID,
            "STUDENT_ID" => $credentials["STUDENT_ID"],
            "YEAR_LEVEL" => $credentials["YEAR_LEVEL"],
            "DEPARTMENT_ID" => $credentials["DEPARTMENT_ID"],
            "COURSE_ID" => $credentials["COURSE_ID"],
        ]);


        if (!$studentCredentials) {
            $res->status(400)->redirect("/student/create", ["error" => "Create Student Details Failed"]);
        }


        return $res->status(200)->redirect("/student/create", ["success" => "Student created successfully"]);
    }

    /**
     * Update user controller
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function updateStudent(Request $req, Response $res)
    {

        $student_id = $req->query["id"];
        $credentials = $req->body;

        $studentModel = new Model("STUDENT");
        $accountModel = new Model("USERS");

        $student_credentials = $studentModel->findOne(["ID" => $student_id]);
        $account_credentials = $accountModel->findOne(["ID" => $student_credentials["USER_ID"]]);

        if (!$student_credentials || !$account_credentials) {
            $res->status(400)->redirect("/student/update?id=" . $student_id, ["error" => "Student does'nt exist "]);
        }

        $updated_account_credentials = $accountModel->updateOne([
            "FIRST_NAME" => $credentials["FIRST_NAME"],
            "LAST_NAME" => $credentials["LAST_NAME"],
            "PHONE_NUMBER" => $credentials["PHONE_NUMBER"],
            "GENDER" => $credentials["GENDER"],
            "ADDRESS" => $credentials["ADDRESS"],
            "EMAIL" => $credentials["EMAIL"],
        ], ["ID" => $student_credentials["USER_ID"]]);

        if (!$updated_account_credentials) {
            $res->status(400)->redirect("/student/update?id=" . $student_id, ["error" => "Update Student Account Failed"]);
        }

        $updated_student_credentials = $studentModel->updateOne([
            "STUDENT_ID" => $credentials["STUDENT_ID"],
            "YEAR_LEVEL" => $credentials["YEAR_LEVEL"],
            "DEPARTMENT_ID" => $credentials["DEPARTMENT_ID"],
            "COURSE_ID" => $credentials["COURSE_ID"],
        ], ["ID" => $student_id]);


        if (!$updated_student_credentials) {
            $res->status(400)->redirect("/student/update?id=" . $student_id, ["error" => "Update Student Details Failed"]);
        }


        $res->status(200)->redirect("/student/update?id=" . $student_id, ["success" => "Update Successfully"]);
    }

    /**
     * Delete user controller
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function deleteStudent(Request $req, Response $res)
    {
        $UID = $req->query["id"];
        $accountModel = new Model("USERS");
        $studentModel = new Model("STUDENT");



        $existStudent = $studentModel->findOne(["ID" => $UID], ["select" => "ID, USER_ID"]);
        if (!$existStudent) {
            return $res->status(400)->redirect("/student", ["error" => "Student doesn't exist"]);
        }


        $existingAccount = $accountModel->findOne(["ID" => $existStudent["USER_ID"]], ["select" => "ID"]);
        if (!$existingAccount) {
            return $res->status(400)->redirect("/student", ["error" => "Account doesn't exist"]);
        }


        // delete the account
        $deleteAccount = $accountModel->deleteOne(["ID" => $existStudent["USER_ID"]]);
        if (!$deleteAccount) {
            return $res->status(400)->redirect("/student", ["error" => "Deletion Failed"]);
        }


        // delete the student
        $deletedStudent = $studentModel->deleteOne(["ID" => $UID]);
        if (!$deletedStudent) {
            return $res->status(400)->redirect("/student", ["error" => "Deletion Failed"]);
        }

        $res->status(200)->redirect("/student", ["success" => "Deleted Successfully"]);


    }

    public static function importStudent(Request $req, Response $res)
    {
        $credentials = $req->body;
        $UID = Uuid::uuid4()->toString();

        $studentModel = new Model("STUDENT");
        $accountModel = new Model("USERS");

        if (isset($_FILES['excelFile'])) {

            $fileName = $_FILES['excelFile']['tmp_name'];
            $fileType = IOFactory::identify($fileName);  // Determine the file type (XLSX, XLS)
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($fileName);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();  // Get all data as array


            if ($fileType != "Xlsx") {
                $res->status(400)->redirect("/student", ["error" => "File format is invalid"]);
            }

            // remove the first
            $header = $sheetData[0];
            $dataRows = array_slice($sheetData, 1);

            $studentsData = [];
            foreach ($dataRows as $row) {
                $student = [];
                foreach ($header as $index => $columnName) {
                    // Handle empty column names gracefully
                    if ($columnName) {
                        $student[$columnName] = $row[$index] ?? null;
                    }
                }
                $studentsData[] = $student;
            }




            foreach ($studentsData as $credentials) {
                $existingEmail = $accountModel->findOne(["EMAIL" => $credentials["EMAIL"],]);


                if ($existingEmail) {
                    $res->status(400)->redirect("/student", ["error" => "Email  Already exist"]);
                }


                $existingPhoneNumber = $accountModel->findOne(["PHONE_NUMBER" => $credentials["PHONE_NUMBER"],]);

                if ($existingPhoneNumber) {

                }

                $existingStudent = $studentModel->findOne(["STUDENT_ID" => $credentials["STUDENT_ID"]]);

                if ($existingStudent) {
                    $res->status(400)->redirect("/student", ["error" => "Student Already exist"]);
                }

                $hashed_password = password_hash($credentials["PASSWORD"], PASSWORD_BCRYPT, ["cost" => 10]);

                // create the account
                $accountCredentials = $accountModel->createOne([
                    "ID" => $UID,
                    "FIRST_NAME" => $credentials["FIRST_NAME"],
                    "LAST_NAME" => $credentials["LAST_NAME"],
                    "PHONE_NUMBER" => $credentials["PHONE_NUMBER"],
                    "GENDER" => $credentials["GENDER"],
                    "ADDRESS" => $credentials["ADDRESS"],
                    "EMAIL" => $credentials["EMAIL"],
                    "PASSWORD" => $hashed_password,
                    "ROLE" => "student"
                ]);

                if (!$accountCredentials) {
                    $res->status(400)->redirect("/student", ["error" => "Create Student Account Failed"]);
                }

                $studentCredentials = $studentModel->createOne([
                    "ID" => Uuid::uuid4()->toString(),
                    "USER_ID" => $UID,
                    "STUDENT_ID" => $credentials["STUDENT_ID"],
                    "YEAR_LEVEL" => $credentials["YEAR_LEVEL"],
                    "DEPARTMENT_ID" => null,
                    "COURSE_ID" => null,
                ]);


                if (!$studentCredentials) {
                    $res->status(400)->redirect("/student", ["error" => "Create Student Details Failed"]);
                }


            }
            return $res->status(200)->redirect("/student", ["success" => "Student created successfully"]);

        } else {
            $res->status(400)->redirect("/student", ["error" => "File not uploaded"]);
        }








    }



    protected static function userExist($email, $phone_number)
    {
        return self::getBaseModel()->findOne([
            "#or" => ["EMAIL" => $email, "PHONE_NUMBER" => $phone_number]
        ], ["select" => "ID"]);
    }
}