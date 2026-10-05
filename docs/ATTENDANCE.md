# Attendance Calculation

## Check-in

1. Capture live camera image (base64) + GPS + accuracy + device info
2. Reject if image missing
3. Resolve employee shift (roster → assignment → default)
4. Compute distance to branch with Haversine (meters)
5. Flag `outside_radius` when distance > branch radius (unless remote allowed)
6. Flag `low_gps_accuracy` when accuracy > configured threshold
7. Compare server time to shift start + grace → `present` or `late`
8. Persist attendance, image, and location records in a transaction

## Check-out

1. Require an open check-in for the day/shift
2. Capture fresh image + location
3. Work minutes = checkout − checkin − break minutes
4. Overtime when work exceeds shift expected minutes / overtime rule
5. Early departure when checkout before shift end − early grace
6. Close active attendance record

## Statuses

`present`, `late`, `absent`, `half_day`, `on_leave`, `holiday`, `weekend`, `remote`, `manual`, `missing_checkout`

## Verification

`verified`, `pending_review`, `outside_radius`, `low_gps_accuracy`, `manual_entry`, `remote_attendance`, `rejected`
