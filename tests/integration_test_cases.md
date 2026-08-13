# Smart Gym Management System
## Sprint 1 Integration Test Cases

### Purpose

These tests verify that the frontend, PHP backend, MySQL database,
authentication system and member dashboard operate together as one
integrated Smart Gym Management System.

| Test ID | Integration Scenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| INT-01 | Submit a new member registration through the registration interface | Registration data should be processed by the PHP backend and the user should be redirected to Login | Registration completed successfully and Login page displayed a success message | PASS |
| INT-02 | Verify registered account in users table | A new users record should be created with Member role, Active account status and securely hashed password | User record created successfully and password stored as a secure hash | PASS |
| INT-03 | Verify registered member profile in members table | Member personal information should be linked to the created user account | Member profile was correctly created and linked to the user record | PASS |
| INT-04 | Verify selected membership in memberships table | Selected membership should be linked to the new member | Annual membership was created successfully with Pending status and valid start/end dates | PASS |
| INT-05 | Login using the newly registered member account | Valid credentials should authenticate the user and create a session | Login succeeded and authenticated session was created | PASS |
| INT-06 | Verify role-based redirect after login | Member account should automatically redirect to the Member Dashboard | User was redirected to member/dashboard.php | PASS |
| INT-07 | Verify Member Dashboard database integration | Dashboard should display information belonging to the authenticated member | Annual membership, Pending status, profile details and dashboard statistics displayed correctly | PASS |
| INT-08 | Verify logout integration | Logout should destroy the authenticated session and return the user to Login | Session ended and user was redirected to Login successfully | PASS |

## Test Account Used

Email: integrationtest@example.com

Role: Member

Membership: Annual

Membership Status: Pending

## Integration Summary

Total Integration Test Cases: 8

Passed: 8

Failed: 0

The Sprint 1 integration testing confirmed that the Smart Gym
Management System frontend, PHP backend, MySQL database,
authentication system and Member Dashboard successfully operate
together as an integrated web application.