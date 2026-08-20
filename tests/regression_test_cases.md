# Smart Gym Management System
## Sprint 1 Regression Testing

Tester: Kaushal Parajuli  
Test Type: Manual Regression Testing

## Purpose

The purpose of this testing is to confirm that the main functions of the Smart Gym Management System continue to work correctly after Sprint 1 integration.

---

## RT-01 Valid Member Registration

**Test:**  
Register a new member using valid information.

**Expected Result:**  
Registration succeeds and records are created in the users, members and memberships tables.

**Actual Result:**  
Registration completed successfully and records were created in the users, members and memberships tables.

**Status:**  
PASS

---

## RT-02 Duplicate Email Registration

**Test:**  
Attempt to register using an email address that already exists.

**Expected Result:**  
The system rejects the duplicate email and does not create another user record.

**Actual Result:**  
The system rejected the duplicate email and no duplicate user record was created.

**Status:**  
PASS

---

## RT-03 Invalid Email Format

**Test:**  
Enter an invalid email address during registration.

**Expected Result:**  
The system displays a validation error and registration does not continue.

**Actual Result:**  
The system displayed a validation error and prevented registration.

**Status:**  
PASS

---

## RT-04 Password Validation

**Test:**  
Attempt registration using a password that does not meet the required password rules.

**Expected Result:**  
The system rejects the password and displays a validation message.

**Actual Result:**  
The system rejected the password because it did not meet the required password rules.

**Status:**  
PASS

---

## RT-05 Valid Member Login

**Test:**  
Login using a valid member account.

**Expected Result:**  
The user is authenticated and redirected to the Member Dashboard.

**Actual Result:**  
The member logged in successfully and was redirected to the Member Dashboard.

**Status:**  
PASS

---

## RT-06 Incorrect Password

**Test:**  
Enter the correct email address with an incorrect password.

**Expected Result:**  
Login is rejected.

**Actual Result:**  
The system rejected the login attempt when an incorrect password was entered.

**Status:**  
PASS

---

## RT-07 Member Role Protection

**Test:**  
Login as a member and attempt to access the Administrator Dashboard.

**Expected Result:**  
Access is denied.

**Actual Result:**  
The member was prevented from accessing the Administrator Dashboard.

**Status:**  
PASS

---

## RT-08 Administrator Login

**Test:**  
Login using a valid administrator account.

**Expected Result:**  
The Administrator Dashboard opens successfully.

**Actual Result:**  
The administrator account logged in successfully and the Administrator Dashboard opened.

**Status:**  
PASS

---

## RT-09 Trainer Login

**Test:**  
Login using a valid trainer account.

**Expected Result:**  
The Trainer Dashboard opens successfully.

**Actual Result:**  
The trainer account logged in successfully and the Trainer Dashboard opened.

**Status:**  
PASS

---

## RT-10 Logout and Session Protection

**Test:**  
Login, logout, and then attempt to reopen a protected dashboard.

**Expected Result:**  
The user cannot access the protected dashboard without logging in again.

**Actual Result:**  
After logout, the protected dashboard could not be accessed without logging in again.

**Status:**  
PASS

---

## Testing Summary

Total Test Cases: 10  
Passed: 10  
Failed: 0  
Not Run: 0  

All planned Sprint 1 regression tests passed successfully. Registration, authentication, password validation, role-based access and session protection remained functional after system integration.
