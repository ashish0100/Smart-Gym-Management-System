# Smart Gym Management System
## Sprint 2 Integration and System Testing

**Jira Task:** SGMS-21  
**Test Type:** Integration and System Testing  
**System:** Smart Gym Management System – World Fitness Australia

---

## Objective

The purpose of this testing is to verify that the Sprint 2 modules work correctly together after integration into the develop branch.

Sprint 2 includes:

- Class booking and cancellation
- Attendance management
- Payment management
- Membership management
- User notifications
- Trainer dashboard improvements
- Administrator dashboard improvements
- Reporting and analytics

---

## Test Environment

- macOS
- XAMPP
- Apache
- MariaDB / MySQL
- PHP
- Safari
- phpMyAdmin
- Smart Gym database: `smart_gym`

---

## Integration Test Cases

### SIT-01 – Administrator Login

**Action:**  
Log in using a valid administrator account.

**Expected Result:**  
Administrator is authenticated and redirected to the Administrator Dashboard.

**Result:** PASS

---

### SIT-02 – Administrator Dashboard Data

**Action:**  
Open the Administrator Dashboard.

**Expected Result:**  
Dashboard displays live statistics for members, memberships, bookings, revenue, trainers, classes, attendance and notifications.

**Result:** PASS

---

### SIT-03 – Class Management Integration

**Action:**  
Open Class Management from the Administrator Dashboard.

**Expected Result:**  
Administrator can view existing classes and create/manage gym classes.

**Result:** PASS

---

### SIT-04 – Member Class Booking

**Action:**  
Log in as a member and book an available class.

**Expected Result:**  
Booking is successfully created and stored in the `bookings` table.

**Result:** PASS

---

### SIT-05 – Duplicate Booking Prevention

**Action:**  
Attempt to book the same class again using the same member.

**Expected Result:**  
The system prevents a duplicate active booking.

**Result:** PASS

---

### SIT-06 – Booking Capacity

**Action:**  
View a class after a booking is created.

**Expected Result:**  
The booked count and remaining capacity are updated correctly.

**Result:** PASS

---

### SIT-07 – Booking Cancellation

**Action:**  
Cancel an existing member class booking.

**Expected Result:**  
Booking status is updated and the class capacity becomes available again.

**Result:** PASS

---

### SIT-08 – Re-booking After Cancellation

**Action:**  
Book a class again after cancelling the previous booking.

**Expected Result:**  
The member can successfully create a new confirmed booking.

**Result:** PASS

---

### SIT-09 – Attendance Management

**Action:**  
Open Attendance Management and record attendance for a member.

**Expected Result:**  
Attendance is stored in the `attendance` table with the correct member, class and status.

**Result:** PASS

---

### SIT-10 – Trainer Dashboard Integration

**Action:**  
Log in using a trainer account.

**Expected Result:**  
Trainer Dashboard displays assigned classes, booking statistics, attendance information and notification information.

**Result:** PASS

---

### SIT-11 – Trainer Attendance Navigation

**Action:**  
Open Attendance Management from the Trainer Dashboard.

**Expected Result:**  
Trainer can access attendance functionality for assigned classes.

**Result:** PASS

---

### SIT-12 – Payment Management

**Action:**  
Log in as administrator and record a payment for a member.

**Expected Result:**  
Payment is saved successfully in the `payments` table.

**Result:** PASS

---

### SIT-13 – Member Payment History

**Action:**  
Log in as the member associated with the payment.

**Expected Result:**  
The recorded payment appears in the member's payment history.

**Result:** PASS

---

### SIT-14 – Membership Management

**Action:**  
Open Membership Management as administrator.

**Expected Result:**  
Administrator can view membership information and update status, dates, access type and remaining visits.

**Result:** PASS

---

### SIT-15 – Notification Creation

**Action:**  
Send a notification from the administrator notification page.

**Expected Result:**  
Notification is stored successfully in the `notifications` table.

**Result:** PASS

---

### SIT-16 – Member Notification Delivery

**Action:**  
Log in as the selected member and open Notifications.

**Expected Result:**  
The notification sent by the administrator appears in the member inbox.

**Result:** PASS

---

### SIT-17 – Notification Read Status

**Action:**  
Mark the notification as read.

**Expected Result:**  
Unread count decreases and `is_read` is updated in the database.

**Result:** PASS

---

### SIT-18 – Reporting and Analytics

**Action:**  
Open Reporting & Analytics from the Administrator Dashboard.

**Expected Result:**  
The page displays live statistics for members, trainers, classes, memberships, bookings, payments, attendance and revenue.

**Result:** PASS

---

### SIT-19 – Printable Report

**Action:**  
Click the Print Report button.

**Expected Result:**  
The browser print interface opens and displays the formatted gym report.

**Result:** PASS

---

### SIT-20 – Role-Based Access Control

**Action:**  
Attempt to access administrator-only pages using a member or trainer account.

**Expected Result:**  
Access is denied.

**Result:** PASS

---

### SIT-21 – Member Role Protection

**Action:**  
Attempt to access member-only functionality without a valid member session.

**Expected Result:**  
The user is redirected or denied access.

**Result:** PASS

---

### SIT-22 – Trainer Role Protection

**Action:**  
Attempt to access the Trainer Dashboard without a trainer account.

**Expected Result:**  
Access is denied.

**Result:** PASS

---

### SIT-23 – Database Integration

**Action:**  
Verify records in phpMyAdmin after booking, attendance, payment and notification operations.

**Expected Result:**  
Changes performed through the web application are correctly reflected in the relevant database tables.

**Result:** PASS

---

### SIT-24 – Navigation Integration

**Action:**  
Test navigation between Administrator Dashboard, Classes, Memberships, Payments, Attendance, Notifications and Reports.

**Expected Result:**  
All implemented module links open the correct pages.

**Result:** PASS

---

### SIT-25 – Responsive Interface

**Action:**  
Resize the browser and inspect major Sprint 2 pages.

**Expected Result:**  
Content remains usable and major cards/tables respond appropriately to smaller screen sizes.

**Result:** PASS

---

# Test Summary

| Result | Number |
|---|---:|
| Total Test Cases | 25 |
| Passed | 25 |
| Failed | 0 |
| Not Run | 0 |

## Overall Result

Sprint 2 integration testing confirms that the implemented Smart Gym Management System modules operate successfully together.

Class booking, attendance, payments, memberships, notifications, trainer functionality, administrator functionality and reporting are successfully connected to the shared Smart Gym database.

No critical integration defects were identified during final Sprint 2 testing.