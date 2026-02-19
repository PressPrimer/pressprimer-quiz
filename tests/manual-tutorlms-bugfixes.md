# TutorLMS Integration Bug Fixes — Manual Test Plan

These tests cover 4 bug fixes in `class-ppq-tutorlms.php` reported by the Tutor LMS dev team.

---

## Bug 1 — Public course quiz visibility

Quiz should display when a TutorLMS course has "No enrollment requirement".

### Setup
- Create a TutorLMS course with **"No enrollment requirement"** (public course)
- Add a lesson to the course
- Attach a PPQ quiz to the lesson

### Tests

- [ ] Visit the lesson as a **logged-out visitor** — quiz should display
- [ ] Visit the lesson as a **logged-in non-enrolled user** — quiz should display
- [ ] Visit the lesson as an **admin** — quiz should display

---

## Bug 2 — Post-completion quiz visibility

Quiz should remain visible after a student finishes/completes a course.

### Setup
- Create a TutorLMS course (with enrollment required)
- Add a lesson with a PPQ quiz attached
- Enroll a student in the course

### Tests

- [ ] Enrolled student visits the lesson — quiz displays
- [ ] Student completes all course content (course status becomes "completed")
- [ ] Student revisits the lesson — quiz should **still display**
- [ ] Verify TutorLMS enrollment record still exists with `post_status = 'completed'`

---

## Bug 3 — Instructor quiz creation permissions

TutorLMS instructors should be able to create and manage their own PPQ quizzes.

### Setup
- Ensure a user with the `tutor_instructor` role exists

### Tests

- [ ] Log in as a `tutor_instructor` user
- [ ] Navigate to **PPQ > Quizzes** — menu should be accessible
- [ ] Create a new quiz — should succeed
- [ ] Edit the quiz — should succeed
- [ ] Verify the instructor does **NOT** see other instructors' quizzes (only their own)
- [ ] **Edge case:** Use a role editor plugin to remove `pressprimer_quiz_manage_own` from `tutor_instructor`, reload any page, verify the cap is re-added

---

## Bug 4 — Lesson auto-completion after quiz pass

When "Require passing score to complete lesson" is enabled, passing the quiz should mark the lesson complete and show the Complete button.

### Setup
- Create a TutorLMS course with enrollment required
- Add a lesson with a PPQ quiz attached
- Enable **"Require passing score to complete lesson"** on the lesson
- Enroll a student in the course

### Tests — Before passing

- [ ] Student visits the lesson — **Complete button is hidden**, quiz is visible
- [ ] Student takes and **fails** the quiz — Complete button remains **hidden**

### Tests — After passing

- [ ] Student takes and **passes** the quiz — lesson should be auto-marked as complete
- [ ] **Reload the page** — Complete button should be visible, or lesson shows "Completed" state
- [ ] Take the quiz again (if retakes are allowed) — Complete button should remain visible regardless of the new attempt result

### Tests — Course auto-completion

- [ ] Enable **"Auto-complete course when all lessons are done"** in TutorLMS Settings > Course
- [ ] Complete all other lessons in the course first
- [ ] Pass the quiz on the final remaining lesson
- [ ] The **course should auto-complete** without needing to visit the course page

---

## Regression Tests

These verify existing behavior is not broken.

- [ ] Enrolled student in a **normal (non-public)** course — quiz displays as before
- [ ] Lesson with quiz but **"Require passing score" disabled** — Complete button is never hidden
- [ ] Lesson **without any PPQ quiz** — Complete button behavior is unchanged
- [ ] **Non-enrolled, non-public** course — quiz shows "Enroll in this course to access the quiz" message
- [ ] **Logged-out user** on a non-public course lesson — quiz does not display
