CREATE TABLE USERS (
ID VARCHAR(255) PRIMARY KEY,
FIRST_NAME VARCHAR(100) NOT NULL,
LAST_NAME VARCHAR(100) NOT NULL,
GENDER VARCHAR(64) NOT NULL,
PHONE_NUMBER VARCHAR(64) NOT NULL UNIQUE,
EMAIL VARCHAR(64) NOT NULL UNIQUE,
PASSWORD VARCHAR(64) NOT NULL,
CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);