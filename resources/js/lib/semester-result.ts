/**
 * Where one semester's results live. A student reaches their own under
 * /student/results; staff go through the student's id. The session travels
 * as 2025-2026 because the stored 2025/2026 form contains a slash.
 */
export function semesterResultUrl(
    semester: { session: string; semester: number },
    studentId: number | undefined,
    isOwnResults: boolean,
): string {
    const base = isOwnResults
        ? '/student/results'
        : `/students/${studentId}/results`;

    return `${base}/${semester.session.replace('/', '-')}/${semester.semester}`;
}
