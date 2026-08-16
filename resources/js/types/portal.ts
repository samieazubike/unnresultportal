/** Shapes shared across the result portal pages. */

export type UserRole =
    'student' | 'lecturer' | 'exam_officer' | 'ict_director' | 'admin';

export type ResultStatus = 'draft' | 'submitted' | 'approved' | 'published';

export type StudentSummary = {
    id?: number;
    name: string;
    reg_number: string;
    department: string;
    faculty: string;
    entry_year: number;
};

export type Standing = {
    cgpa: number;
    classification: string;
    semesters_completed?: number;
    credit_units_passed: number;
};

export type CourseResult = {
    course_code: string;
    course_title: string;
    credit_units: number;
    ca_score: number;
    exam_score: number;
    total_score: number;
    grade: string;
    grade_point: number;
};

export type SessionResults = {
    session: string;
    semester: number;
    label: string;
    gpa: number;
    credit_units: number;
    courses: CourseResult[];
};

/**
 * A session whose results exist but have not been released. Note it carries
 * no scores at all — an unpublished result is absent from the payload, not
 * merely hidden in the interface (README §9).
 */
export type AwaitingRelease = {
    session: string;
    semester: number;
    label: string;
    courses: number;
};

export type FailureRate = {
    course_code: string;
    course_title: string;
    session: string;
    enrolled: number;
    failed: number;
    failure_rate: number;
    flagged: boolean;
};

export type DepartmentPerformance = {
    department: string;
    faculty: string;
    students: number;
    average_cgpa: number;
    passes: number;
    failures: number;
};

export type FacultyPerformance = {
    faculty: string;
    departments: number;
    students: number;
    average_cgpa: number;
    passes: number;
    failures: number;
};

export type InstitutionSummary = {
    students: number;
    courses: number;
    published_results: number;
    pending_review: number;
    awaiting_release: number;
    flagged_courses: number;
};

export type Recommendation = {
    body: string;
    source: 'llm' | 'fallback';
    flags: string[];
    generated_at: string;
};

export type ScoreRow = {
    registration_id: number;
    reg_number: string;
    student_name: string;
    ca_score: number | null;
    exam_score: number | null;
    total_score: number | null;
    grade: string | null;
    status: ResultStatus;
    editable: boolean;
    last_edited_by: string | null;
    edited_after_submission: boolean;
};

export type TeachingTeamMember = {
    id: number;
    name: string;
    is_head: boolean;
};

export type ImportError = {
    row: number;
    reg_number: string;
    message: string;
};

/**
 * A row read out of an uploaded spreadsheet. Staged into the score sheet's
 * inputs as an unsaved edit — nothing is written until the lecturer saves.
 */
export type StagedScore = {
    registration_id: number;
    reg_number: string;
    ca_score: number;
    exam_score: number;
};
