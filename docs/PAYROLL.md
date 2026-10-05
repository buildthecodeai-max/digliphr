# Payroll Calculation

## Flow

1. Create payroll period
2. Load active employees
3. Finalize attendance for the period
4. Reconcile approved leave
5. Calculate earnings (basic, allowances, OT, bonus)
6. Calculate deductions (late, absence, unpaid leave, tax, loan, advance)
7. Gross / Net
8. HR review → approval → lock
9. Generate payslip PDFs
10. Mark payment status

## Proration

Joiners/leavers are prorated by payable days in the period.

## Overtime

Approved overtime minutes × configured rate multiplier are added as earnings.

## Loans / Advances

Due installments and advance recoveries for the period are auto-added as deductions when payroll is processed.
