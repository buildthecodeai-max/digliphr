-- ============================================================
-- Standardise all shifts: 60-min break, 8-hour expected work
-- Apply half-day rule from weekly patterns (no break on half days)
-- ============================================================

-- 1. Set break_minutes = 60 and expected_work_minutes = 480 (8 h) on all active shifts.
UPDATE shifts
SET break_minutes         = 60,
    expected_work_minutes = 480,
    updated_at            = NOW()
WHERE deleted_at IS NULL;

-- 2. Set expected_work_minutes = 480, break_minutes = 60 on all non-archived, non-half-day rows.
UPDATE attendance a
LEFT JOIN shifts s ON s.id = a.shift_id
SET
    a.expected_work_minutes = COALESCE(s.expected_work_minutes, 480),
    a.break_minutes         = 60,
    a.updated_at            = NOW()
WHERE a.deleted_at IS NULL
  AND a.status != 'half_day';

-- 3. Recalculate work_minutes / overtime_minutes for completed FULL-DAY records.
UPDATE attendance a
LEFT JOIN shifts s ON s.id = a.shift_id
SET
    a.work_minutes = CASE
        WHEN DATE(a.check_out_at) > a.attendance_date
            THEN 480
        ELSE
            LEAST(
                GREATEST(0, TIMESTAMPDIFF(MINUTE, a.check_in_at, a.check_out_at) - 60),
                480
            )
    END,
    a.overtime_minutes = CASE
        WHEN DATE(a.check_out_at) > a.attendance_date
            THEN 0
        ELSE
            GREATEST(0,
                GREATEST(0, TIMESTAMPDIFF(MINUTE, a.check_in_at, a.check_out_at) - 60)
                - 480
                - COALESCE(s.overtime_after_minutes, 0)
            )
    END,
    a.updated_at = NOW()
WHERE
    a.deleted_at      IS NULL
    AND a.check_in_at  IS NOT NULL
    AND a.check_out_at IS NOT NULL
    AND a.status != 'half_day';

-- 4. Fix explicitly-marked half_day records:
--    no break, raw duration as work_minutes, expected = 240 (half of 8 h).
UPDATE attendance a
SET
    a.work_minutes          = GREATEST(0, TIMESTAMPDIFF(MINUTE, a.check_in_at, a.check_out_at)),
    a.overtime_minutes      = 0,
    a.break_minutes         = 0,
    a.expected_work_minutes = 240,
    a.updated_at            = NOW()
WHERE
    a.deleted_at      IS NULL
    AND a.check_in_at  IS NOT NULL
    AND a.check_out_at IS NOT NULL
    AND a.status = 'half_day';

-- 5. Fix records whose attendance_date is a PATTERN HALF-DAY for the employee,
--    even if not already marked half_day.
UPDATE attendance a
INNER JOIN employees e ON e.id = a.employee_id
LEFT JOIN designations dsg ON dsg.id = e.designation_id AND dsg.deleted_at IS NULL
LEFT JOIN attendance_week_patterns mgr_pat
    ON mgr_pat.company_id = e.company_id AND mgr_pat.is_manager_pattern = 1 AND mgr_pat.deleted_at IS NULL
LEFT JOIN departments dept ON dept.id = e.department_id
LEFT JOIN attendance_week_patterns dept_pat
    ON dept_pat.id = dept.week_pattern_id AND dept_pat.deleted_at IS NULL
LEFT JOIN attendance_week_patterns def_pat
    ON def_pat.company_id = e.company_id AND def_pat.is_default = 1 AND def_pat.deleted_at IS NULL
SET
    a.status                = 'half_day',
    a.work_minutes          = GREATEST(0, TIMESTAMPDIFF(MINUTE, a.check_in_at, a.check_out_at)),
    a.overtime_minutes      = 0,
    a.break_minutes         = 0,
    a.expected_work_minutes = 240,
    a.updated_at            = NOW()
WHERE
    a.deleted_at      IS NULL
    AND a.check_in_at  IS NOT NULL
    AND a.check_out_at IS NOT NULL
    AND a.status      != 'half_day'
    AND (
        CASE
            WHEN dsg.is_manager_or_above = 1 AND mgr_pat.id IS NOT NULL THEN
                CASE WEEKDAY(a.attendance_date)
                    WHEN 0 THEN mgr_pat.monday_type    WHEN 1 THEN mgr_pat.tuesday_type
                    WHEN 2 THEN mgr_pat.wednesday_type WHEN 3 THEN mgr_pat.thursday_type
                    WHEN 4 THEN mgr_pat.friday_type    WHEN 5 THEN mgr_pat.saturday_type
                    ELSE mgr_pat.sunday_type END
            WHEN dept_pat.id IS NOT NULL THEN
                CASE WEEKDAY(a.attendance_date)
                    WHEN 0 THEN dept_pat.monday_type    WHEN 1 THEN dept_pat.tuesday_type
                    WHEN 2 THEN dept_pat.wednesday_type WHEN 3 THEN dept_pat.thursday_type
                    WHEN 4 THEN dept_pat.friday_type    WHEN 5 THEN dept_pat.saturday_type
                    ELSE dept_pat.sunday_type END
            WHEN def_pat.id IS NOT NULL THEN
                CASE WEEKDAY(a.attendance_date)
                    WHEN 0 THEN def_pat.monday_type    WHEN 1 THEN def_pat.tuesday_type
                    WHEN 2 THEN def_pat.wednesday_type WHEN 3 THEN def_pat.thursday_type
                    WHEN 4 THEN def_pat.friday_type    WHEN 5 THEN def_pat.saturday_type
                    ELSE def_pat.sunday_type END
            ELSE
                CASE WEEKDAY(a.attendance_date) WHEN 5 THEN 'half' WHEN 6 THEN 'off' ELSE 'full' END
        END
    ) = 'half';
