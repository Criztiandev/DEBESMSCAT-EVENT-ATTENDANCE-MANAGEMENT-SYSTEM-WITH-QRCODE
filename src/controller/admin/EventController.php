<?php

namespace controller\admin;

use lib\Mangoose\Model;
use lib\Router\classes\Request;
use lib\Router\classes\Response;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\Uuid;

class EventController
{

    private const BASE_URL = "views/admin/event";
    private const BASE_MODEL = "EVENT";
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
            $eventModel = self::getBaseModel();
            $events = $eventModel->find();

            $transformed_event = array_map(function ($event) {
                $departmentModel = new Model("DEPARTMENT");
                $courseModel = new Model("COURSE");

                $departmentName = $departmentModel->findOne(["ID" => $event["DEPARTMENT_ID"]], ["select" => "NAME"]);
                $courseName = $courseModel->findOne(["ID" => $event["COURSE_ID"]], ["select" => "NAME"]);


                return [
                    ...$event,
                    "DEPARTMENT" => $departmentName,
                    "COURSE" => $courseName,
                ];

            }, $events);


            $res->status(200)->render(self::BASE_URL . "/screen.view.php", ["events" => $transformed_event]);

        } catch (\Exception $e) {
            $res->status(500)->json(["error" => "Failed to fetch Events: " . $e->getMessage()]);
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
            $eventID = $req->query["id"];
            $eventModel = new Model("EVENT");

            $credentials = $eventModel->findOne(["ID" => $eventID]);


            if (!$credentials) {
                $res->status(400)->redirect("/users/update?id=" . $eventID, ["error" => "Event doesn't exist"]);
            }


            $res->status(200)->render(
                self::BASE_URL . "/pages/update.page.php",
                [
                    "UID" => $eventID,
                    "details" => $credentials,
                    "roles" => self::ROLES
                ]
            );



        } catch (\Exception $e) {
            $res->status(500)->json(["error" => "Failed to fetch users: " . $e->getMessage()]);
        }
    }


    public static function renderSessiion(Request $req, Response $res)
    {
        try {
            $attendanceModel = new Model("Attendance");
            $event_id = $req->query["id"];

            // get all the attendance related
            $attendance_related = $attendanceModel->find(["EVENT_ID" => $event_id]);

            $transformed_attendees_list = array_map(function ($items) {
                $studentModel = new Model("STUDENT");
                $accountModel = new Model("USERS");

                $studentCredentials = $studentModel->findOne(["STUDENT_ID" => $items["STUDENT_ID"]]);
                $accountCredentials = $accountModel->findOne(["ID" => $studentCredentials["USER_ID"]], ["select" => "FIRST_NAME, LAST_NAME"]);


                return [
                    ...$items,
                    "STUDENT_NAME" => $accountCredentials["FIRST_NAME"] . " " . $accountCredentials["LAST_NAME"],
                ];


            }, $attendance_related);



            $res->status(200)->render(self::BASE_URL . "/pages/session-start.php", ["EVENT_ID" => $event_id, "student_list" => $transformed_attendees_list]);
        } catch (\Exception $e) {
            $res->status(500)->json(["error" => "Failed to fetch Events: " . $e->getMessage()]);
        }
    }


    // ================================= Actions ======================================================

    /**
     * Create users Handler
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function createEvent(Request $req, Response $res)
    {
        $credentials = $req->body;
        $eventModel = new Model("EVENT");

        $existingEvent = $eventModel->findOne(["NAME" => $credentials["NAME"]]);

        if ($existingEvent) {
            return $res->status(400)->redirect("/event/create", ["error" => "Event already exists"]);
        }

        // Check if the dates are in the past or exceed 7 days in the future
        $currentDate = new \DateTime();
        $startDate = new \DateTime($credentials["START_DATE"]);
        $endDate = new \DateTime($credentials["END_DATE"]);
        $maxFutureDate = (new \DateTime())->modify('+7 days');

        if ($startDate < $currentDate || $endDate < $currentDate) {
            return $res->status(400)->redirect("/event/create", ["error" => "Event dates cannot be in the past"]);
        }

        if ($startDate > $maxFutureDate || $endDate > $maxFutureDate) {
            return $res->status(400)->redirect("/event/create", ["error" => "Event dates cannot exceed 7 days in the future"]);
        }

        $UID = Uuid::uuid4()->toString();
        $createdEvent = $eventModel->createOne([
            "ID" => $UID,
            ...$credentials,
            "STATUS" => "INACTIVE"
        ]);

        if (!$createdEvent) {
            return $res->status(400)->redirect("/event/create", ["error" => "Creating event went wrong"]);
        }

        return $res->status(200)->redirect("/event/create", ["success" => "Event created successfully"]);
    }

    /**
     * Update user controller
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function updateUser(Request $req, Response $res)
    {


        // $res->status(200)->redirect("/users/update?id=" . $UID, ["success" => "Update Successfull"]);
    }

    /**
     * Delete user controller
     * @param \lib\Router\classes\Request $req
     * @param \lib\Router\classes\Response $res
     * @return void
     */
    public static function deleteEvent(Request $req, Response $res)
    {
        $UID = $req->query["id"];
        $eventModel = new Model("EVENT");

        $existEvent = $eventModel->findOne(["ID" => $UID], ["select" => "ID"]);
        if (!$existEvent) {
            return $res->status(400)->redirect("/event", ["error" => "Event doesn't exist"]);
        }


        // delete the event
        $deletedEvent = $eventModel->deleteOne(["ID" => $UID]);
        if (!$deletedEvent) {
            return $res->status(400)->redirect("/event", ["error" => "Deletion Failed"]);
        }

        $res->status(200)->redirect("/event", ["success" => "Deleted Successfully"]);


    }


    protected static function userExist($email, $phone_number)
    {
        return self::getBaseModel()->findOne([
            "#or" => ["EMAIL" => $email, "PHONE_NUMBER" => $phone_number]
        ], ["select" => "ID"]);
    }
}