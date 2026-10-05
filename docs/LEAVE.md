# Leave Calculation

## Chargeable days

For a request from start → end:

1. Count calendar days
2. Subtract weekends (Sat/Sun by default; configurable)
3. Subtract company/branch holidays in range
4. Half-day requests charge `0.5`
5. Remaining days are chargeable against leave balance

## Balance formula

`closing = opening + accrued + carried_forward + adjusted − used − pending − encashed`

## Approval

Configurable one or multi-level workflow. Default:

Employee → Department Manager → HR Manager

## Extension

Approved leave can be extended without overwriting the original request. A separate `leave_extensions` row stores original end, new end, additional days, and reason. Balances and attendance are recalculated.
