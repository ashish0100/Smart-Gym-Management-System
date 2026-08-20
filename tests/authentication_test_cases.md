# Smart Gym Management System
## Authentication Test Cases

### Sprint 1 - Authentication Testing

The following test cases were executed to verify member registration,
login, logout, password security, session management and role-based
access control.

| Test ID | Test Scenario | Test Data / Action | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|
| AUTH-01 | Register a new member with valid details | Complete registration form with valid member information | Member account should be created successfully | Account created and records stored in users, members and memberships tables | PASS |
| AUTH-02 | Register using an existing email address | Use testmember@example.com again | System should reject duplicate email | Duplicate account registration rejected | PASS |
| AUTH-03 | Register with mismatched passwords | Enter different password and confirm password | Registration should be rejected | Validation error displayed | PASS |
| AUTH-04 | Register with password shorter than 8 characters | Enter short password | Registration should be rejected | Password validation error displayed | PASS |
| AUTH-05 | Register without accepting terms | Leave terms checkbox unticked | Registration should be rejected | Terms validation error displayed | PASS |
| AUTH-06 | Login with valid Member account | testmember@example.com / valid password | Member should be redirected to Member Dashboard | Member Dashboard loaded successfully | PASS |
| AUTH-07 | Login with incorrect password | Valid member email with wrong password | Login should be rejected | Incorrect email or password message displayed | PASS |
| AUTH-08 | Login with invalid email format | Enter invalid email | Login should be rejected | Email validation error displayed | PASS |
| AUTH-09 | Login with valid Trainer account | trainer@worldfitness.com / valid password | Trainer should be redirected to Trainer Dashboard | Trainer Dashboard loaded successfully | PASS |
| AUTH-10 | Login with valid Admin account | admin@worldfitness.com / valid password | Admin should be redirected to Admin Dashboard | Admin Dashboard loaded successfully | PASS |
| AUTH-11 | Member attempts to access Admin Dashboard | Logged-in Member manually opens admin/dashboard.php | Access should be denied | Administrator area blocked | PASS |
| AUTH-12 | Member attempts to access Trainer Dashboard | Logged-in Member manually opens trainer/dashboard.php | Access should be denied | Trainer area blocked | PASS |
| AUTH-13 | Trainer attempts to access Admin Dashboard | Logged-in Trainer opens admin/dashboard.php | Access should be denied | Administrator area blocked | PASS |
| AUTH-14 | Trainer attempts to access Member Dashboard | Logged-in Trainer opens member/dashboard.php | Access should be denied | Member area blocked | PASS |
| AUTH-15 | Admin attempts to access Trainer Dashboard | Logged-in Admin opens trainer/dashboard.php | Access should be denied | Trainer area blocked | PASS |
| AUTH-16 | Admin attempts to access Member Dashboard | Logged-in Admin opens member/dashboard.php | Access should be denied | Member area blocked | PASS |
| AUTH-17 | Logout authenticated user | Click Logout | Session should end and user should return to Login page | User logged out and redirected successfully | PASS |
| AUTH-18 | Access dashboard after logout | Manually open protected dashboard after logout | User should be redirected to Login page | Login required before dashboard access | PASS |
| AUTH-19 | Verify password storage | Inspect users table in phpMyAdmin | Plain-text password must not be stored | Hashed password stored using password_hash() | PASS |
Verified in login_process.php that session_regenerate_id(true) executes after successful authentication

## Testing Summary

Total Test Cases: 20

Passed: 20

Failed: 0

Authentication testing confirmed that the Smart Gym Management System
correctly supports secure registration, login, logout, password hashing,
session management and role-based access control for Member, Trainer
and Administrator users.

## Regression Review

Reviewed by: Kaushal Parajuli

The authentication test cases were rechecked during Sprint 1 regression testing.

Registration, login, password validation, role-based access, session protection and logout were verified again after system integration.

Result: PASS
