-- ============================================================
-- Standardise all shifts to 60-min break + 7-hour work day
-- ============================================================

-- 1. Set break_minutes = 60 on all active shifts.
--    Clear explicit expected_work_minutes so the system recalculates
--    it dynamically from (end_time - start_time - 60 min).
UPDATE shifts
SET break_minutes          = 60,
    expected_work_minutes  = NULL,
    updated_at             = NOW()
WHERE deleted_at IS NULL;

-- 2. Recalculate attendance records that have both check-in and check-out.
--    For same-day records:
--      work_minutes   = (checkout - checkin) in minutes - 60
--      capped at expected_work_minutes on the attendance row (or 420 min / 7 h)
--    For next-day checkouts (forgot to clock out):
--      work_minutes   = expected (420 min) — cannot exceed one working day
--      overtime_minutes = 0 (no valid overtime when checkout was next day)

UPDATE attendance a
LEFT JOIN shifts s ON s.id = a.shift_id
SET
    a.break_minutes = 60,

    -- Raw minutes worked minus break
    a.work_minutes = CASE
        WHEN DATE(a.check_out_at) > a.attendance_date
            -- Next-day checkout: cap at shift expected (or 420)
            THEN COALESCE(a.expected_work_minutes, s.expected_work_minutes, 420)
        ELSE
            LEAST(
                GREATEST(0, TIMESTAMPDIFF(MINUTE, a.check_in_at, a.check_out_at) - 60),
                COALESCE(a.expected_work_minutes, s.expected_work_minutes, 420)
            )
    END,

    -- Overtime: only valid for same-day records; zero for next-day checkouts
    a.overtime_minutes = CASE
        WHEN DATE(a.check_out_at) > a.attendance_date
            THEN 0
        ELSE
            GREATEST(0,
                GREATEST(0, TIMESTAMPDIFF(MINUTE, a.check_in_at, a.check_out_at) - 60)
                - COALESCE(a.expected_work_minutes, s.expected_work_minutes, 420)
                - COALESCE(s.overtime_after_minutes, 0)
            )
    END,

    a.updated_at = NOW()

WHERE
    a.deleted_at    IS NULL
    AND a.check_in_at  IS NOT NULL
    AND a.check_out_at IS NOT NULL;
