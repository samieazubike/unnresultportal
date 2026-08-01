# Result Processing System with Data Visualization

Final-year B.Sc project, Department of Computer Science, University of Nigeria, Nsukka (UNN).

This README is the authoritative implementation spec. The written project (Chapters One to Five) has already been submitted and defended against this design, so **the code must match what is documented here**. If an implementation choice would contradict this file, stop and flag it rather than silently diverging.

---

## 1. What the system does

Two things, in one application:

1. **Result processing.** Lecturers enter scores, the system computes grades and grade points, an examination officer approves, and an examination officer or ICT director publishes. Students then see results in a portal and download transcripts as PDF.
2. **Data visualization.** The stored results are read back as charts: CGPA trend per student, grade distribution per course, departmental and faculty performance, course failure-rate flags, and a per-student recommendation.

The contribution being claimed is the *integration* of the two, with role-specific dashboards. The charts are not decoration; they are the point.

---

## 2. Stack (fixed, do not substitute)

| Layer | Technology | Notes |
|---|---|---|
| Backend | **Laravel** (PHP 8.2+) | Routing, Eloquent ORM, controllers, validation |
| Database | **PostgreSQL 16** | Aggregate + window functions carry the analytics |
| Frontend | **React** | Page components |
| Glue | **Inertia.js** | Connects Laravel to React. **No REST API.** |
| Styling | **Tailwind CSS** | Utility classes |
| Components | **shadcn/ui** | Copied into the repo, not installed as a black box |
| Charts | **shadcn/ui chart** (wraps **Recharts**) | All visualizations |
| PDF | **DomPDF** (`barryvdh/laravel-dompdf`) | Transcript generation |
| Desktop | **NativePHP** | Secondary deliverable: an Electron shell over the running web app |
| Bundler | **Vite** | Laravel default |

### Hard architectural rules

- **Do NOT build a REST API.** Controllers return `Inertia::render('Page', [...props])`. There is no `/api` route group and no JSON API for the frontend to consume.
- **Do NOT use Laravel Sanctum or token auth.** Authentication is standard Laravel **session** auth with the `auth` middleware. Sanctum was removed from the design when Inertia replaced Next.js.
- **Do NOT store GPA or CGPA as columns.** They are computed on demand by SQL. See §6.
- **Do NOT compute analytics in PHP loops.** Aggregation happens in PostgreSQL and returns a handful of rows. Pulling thousands of result rows into PHP to sum them is a design violation.

---

## 3. Roles

Five roles on the `users.role` enum:

| Role | Can do |
|---|---|
| `student` | View own published results, own CGPA trend, own recommendation, download own transcript |
| `lecturer` | Enter scores (typed or Excel upload) for assigned courses, submit sheets for review, view own course grade distribution |
| `exam_officer` | Approve/reject submitted sheets, **publish** approved results, view departmental dashboards |
| `ict_director` | **Publish** approved results (institution-wide), view all dashboards |
| `admin` | Manage users, faculties, departments, courses, lecturer assignment, failure-rate threshold |

Alumni are `student` accounts whose programme is complete. They keep read access to results and transcripts. Do not create a separate role for them.

Enforce with policies or middleware. A student must never be able to read another student's result by changing an ID in the URL.

---

## 4. Domain model (7 tables)

Migrations must produce exactly these tables. Column names matter, they are documented in Chapter Three.

```
users(id, name, email, password, role, timestamps)
    role ∈ {student, lecturer, exam_officer, ict_director, admin}

faculties(id, faculty_name UNIQUE)

departments(id, dept_name UNIQUE, faculty_id → faculties)

students(id, user_id → users, reg_number UNIQUE, dept_id → departments, entry_year)

courses(id, course_code UNIQUE, course_title, credit_units, dept_id → departments,
        lecturer_id → users NULL)

registrations(id, student_id → students, course_id → courses, session, semester)
    session  = '2025/2026' style string
    semester ∈ {1, 2}
    UNIQUE(student_id, course_id, session, semester)

results(id, registration_id → registrations UNIQUE,
        ca_score, exam_score, total_score, grade, grade_point,
        status, published_by → users NULL, timestamps)
    status ∈ {draft, submitted, approved, published}
```

### Key relationship (asked about in the defence)

A student is **not** linked directly to a course. A student is linked to a **registration**, a course is linked to a **registration**, and each registration **produces exactly one result**. This is deliberate: a student can retake the same course code in a later session after a failure, and attaching the result to the registration keeps the two attempts as separate unambiguous records.

```
Student 1 ──< Registration >── 1 Course
                   │ 1
                   │
                   ▼ 1
                Result
```

The schema is in **third normal form**. Keep it that way.

---

## 5. Grading rules (UNN five-point scale)

`ca_score` is out of 30. `exam_score` is out of 70. `total_score = ca_score + exam_score`, out of 100.

| Total score | Grade | Grade point |
|---|---|---|
| 70 and above | A | 5 |
| 60 to 69 | B | 4 |
| 50 to 59 | C | 3 |
| 45 to 49 | D | 2 |
| 40 to 44 | E | 1 |
| Below 40 | F | 0 |

Implement once, in a single place (e.g. `App\Support\Grading::forScore(float $total): array`). Never duplicate the boundaries.

**Boundary tests are mandatory.** Assert correct grade at 39, 40, 44, 45, 49, 50, 59, 60, 69, 70. These exact values were named in the project's testing section.

---

## 6. GPA and CGPA (computed, never stored)

```
Semester GPA = Σ(grade_point × credit_units) ÷ Σ(credit_units)   for that session+semester
CGPA         = same expression, accumulated across all completed semesters
```

Only `status = 'published'` results count toward GPA and CGPA.

### Reference query: CGPA trend (the core visualization)

```sql
WITH sem AS (
    SELECT reg.student_id,
           reg.session,
           reg.semester,
           SUM(res.grade_point * c.credit_units) AS weighted_points,
           SUM(c.credit_units)                   AS credit_units
    FROM results res
    JOIN registrations reg ON reg.id = res.registration_id
    JOIN courses c         ON c.id  = reg.course_id
    WHERE res.status = 'published'
      AND reg.student_id = :student_id
    GROUP BY reg.student_id, reg.session, reg.semester
)
SELECT session,
       semester,
       ROUND(weighted_points::numeric / NULLIF(credit_units, 0), 2) AS gpa,
       ROUND(
           (SUM(weighted_points) OVER w)::numeric
           / NULLIF(SUM(credit_units) OVER w, 0), 2
       ) AS cgpa
FROM sem
WINDOW w AS (ORDER BY session, semester ROWS UNBOUNDED PRECEDING)
ORDER BY session, semester;
```

That single query drives the line chart. It is the query most likely to be inspected, so keep it readable.

---

## 7. The five visualization capabilities

All five live in `App\Services\VisualizationService`. Every method returns a small, chart-ready array.

**1. CGPA trend** (student) — the query above. Line chart.

**2. Grade distribution** (course) — bar or pie chart.
```sql
SELECT res.grade, COUNT(*) AS count
FROM results res
JOIN registrations reg ON reg.id = res.registration_id
WHERE reg.course_id = :course_id AND reg.session = :session
  AND res.status = 'published'
GROUP BY res.grade ORDER BY res.grade;
```

**3. Departmental / faculty performance** — comparative bar chart. Average student CGPA and grade counts grouped by department, then by faculty.

**4. Course failure-rate flagging** — table plus flag badge.
```sql
SELECT c.course_code, reg.session,
       COUNT(*)                                        AS enrolled,
       COUNT(*) FILTER (WHERE res.grade = 'F')         AS failed,
       ROUND(100.0 * COUNT(*) FILTER (WHERE res.grade = 'F')
             / NULLIF(COUNT(*), 0), 1)                 AS failure_rate
FROM results res
JOIN registrations reg ON reg.id = res.registration_id
JOIN courses c         ON c.id  = reg.course_id
WHERE res.status = 'published'
GROUP BY c.course_code, reg.session
ORDER BY failure_rate DESC;
```
Flag any course whose `failure_rate` exceeds the admin-configured threshold (store in a `settings` table or config, default 40).

**5. Student recommendation** — see §8.

---

## 8. Recommendation layer (the "AI" part)

Hybrid by design. Be careful here, this is the part most likely to be challenged.

```
SQL computes the facts  →  PHP rules decide what is true  →  LLM phrases it  →  cache
```

1. **Facts (SQL, deterministic):** CGPA slope over the last 2–3 semesters, weakest courses by grade point, any failed prerequisites, current CGPA and classification band.
2. **Rules (PHP):** turn facts into flags, e.g. `cgpa_declining_2_semesters`, `weak_in: [MTH 201, COS 205]`, `at_risk_below_1_5`.
3. **Phrasing (LLM):** send **only the computed facts and flags**, never raw result rows. Instruct the model to use only the supplied data, invent no grade or course, and return three sentences of academic advice.
4. **Cache** the generated text against the student and session in the database.
5. **Fallback:** if the LLM call fails, render a plain rule-based sentence instead. The system must work with no internet.

Requirements 3 to 5 are non-negotiable. The defensibility of this feature rests on every fact in the advice being traceable to a query.

---

## 9. Result workflow (approval ≠ publication)

```
draft ──submit──> submitted ──approve──> approved ──publish──> published
                       │                                  
                       └──reject──> draft (returned to lecturer with a note)
```

- `approved` means **academically accepted**. It does **not** make the result visible to the student.
- `published` is a separate deliberate action by `exam_officer` or `ict_director`. Record who did it in `results.published_by`.
- Until published, the student portal shows **"awaiting release"** for that session.
- GPA, CGPA and every chart count **published results only**.

Do not collapse these two states. The separation is a documented design decision and it will be examined.

---

## 10. Score entry

Two paths, both landing in the same place:

- **Typed entry.** Lecturer opens an assigned course, sees the class list built from `registrations` for that session and semester, enters CA and exam scores inline.
- **Excel upload.** Lecturer uploads `.xlsx`. The system reads it, validates it, and populates the entries. Use `maatwebsite/excel`.

Excel validation must reject and report, not silently skip: unknown registration number, score out of range (CA > 30, exam > 70), duplicate rows, missing columns. Show the lecturer a row-by-row error list before anything is saved. Import inside a transaction.

Grade and grade point are computed on save, never accepted from input.

---

## 11. Suggested structure

```
app/
  Http/Controllers/
    Auth/                        # Laravel Breeze (Inertia + React)
    ResultEntryController.php
    ResultReviewController.php   # approve / reject
    ResultPublicationController.php
    StudentPortalController.php
    TranscriptController.php
    Dashboard/
      StudentDashboardController.php
      LecturerDashboardController.php
      DepartmentDashboardController.php
  Models/                        # User, Faculty, Department, Student, Course, Registration, Result
  Services/
    VisualizationService.php     # the five capabilities
    RecommendationService.php    # facts → rules → LLM → cache
    GradingService.php           # score → grade, grade point
    ExcelResultImporter.php
  Support/Grading.php            # the scale, single source of truth
  Policies/
resources/js/
  Pages/                         # Inertia page components
    Lecturer/ Student/ ExamOfficer/ Admin/
  Components/ui/                 # shadcn/ui
  Components/charts/             # Recharts wrappers
database/
  migrations/
  seeders/                       # simulated UNN dataset
tests/
```

---

## 12. Seed data (simulated, and it must look real)

Real UNN result data was not available (stated as a limitation in Chapter One). The seeder must generate a **realistic simulated dataset**:

- 2 faculties, 3 to 4 departments, one of them Computer Science
- 40 to 60 courses with plausible codes (`COS 421`, `MTH 201`) and credit units of 2 or 3
- 150 to 300 students across 3 to 4 entry years
- 4 to 6 sessions of registrations and results so **the CGPA trend has something to plot**
- Score distribution that is not uniform: cluster around C and B, with a realistic tail of failures
- At least two courses deliberately seeded above the failure threshold, so the flagging feature demonstrably fires
- At least one student with a declining CGPA, so the recommendation layer has a real case to speak to

A demo where every chart is flat or empty is worse than no demo.

---

## 13. Setup

See **[SETUP.md](SETUP.md)** — prerequisites, install, environment reference,
demo accounts, running the web and desktop apps, tests, and troubleshooting.

The short version: `composer install`, `cp .env.example .env`,
`php artisan key:generate`, create the PostgreSQL database,
`php artisan migrate --seed`, `npm install && npm run build`, `composer dev`.

Desktop is secondary, do it last. The window loads the web application over
HTTP rather than querying the database itself: the static PHP binary NativePHP
bundles is built without `pdo_pgsql`, so the desktop process cannot open the
PostgreSQL database directly. That also means the desktop app uses exactly the
same database, pages and session auth as the web app — there is no separate
API, per §2.

---

## 14. Build order

Work in this sequence. Do not start visualization before results can be published, or the charts will have nothing to read.

1. Migrations, models, relationships, factories, seeder
2. Auth (Breeze with Inertia + React) and role-based access
3. Admin CRUD: faculties, departments, courses, users, lecturer assignment
4. Student course registration
5. Score entry (typed) plus `GradingService` and boundary tests
6. Excel upload with row-level validation
7. Review workflow: submit, approve, reject
8. Publication (separate action, `published_by` recorded)
9. Student portal: published results, awaiting-release state
10. `VisualizationService` and the four chart dashboards
11. `RecommendationService` with cache and offline fallback
12. Transcript PDF via DomPDF
13. NativePHP desktop packaging
14. Test pass and screenshots for Chapter Four

---

## 15. Definition of done

Tied to the seven objectives in Chapter One:

- [ ] Lecturer can enter scores by typing and by Excel upload, with validation errors surfaced per row
- [ ] Grades and grade points computed correctly at every boundary value
- [ ] Sheet moves draft → submitted → approved → published, with reject returning it
- [ ] Approved results are provably invisible to students until published
- [ ] Student and alumnus can log in remotely and view published results
- [ ] Transcript downloads as a formatted multi-page PDF
- [ ] CGPA trend renders as a line chart from the window-function query
- [ ] Grade distribution renders per course
- [ ] Departmental and faculty comparison renders
- [ ] Courses above the failure threshold are flagged automatically
- [ ] Recommendation generates, caches, and falls back gracefully with no internet
- [ ] No `/api` routes, no Sanctum, no stored GPA or CGPA columns anywhere
- [ ] Screenshots captured for Chapter Four Figures 4.2 to 4.5

---

## 16. Things that will break the defence

Listed because they are easy to do by accident:

- Adding a `gpa` or `cgpa` column "for performance"
- Letting `approve` publish in one step
- Building a JSON API and calling it with `fetch` from React
- Computing failure rates by looping over results in PHP
- Letting the LLM see raw result rows, or letting it produce a number that is not in the supplied facts
- Hardcoding the grade boundaries in more than one file
- A seeder that produces empty or flat charts
