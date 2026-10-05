# Website activity monitoring

This module records domain-level activity for employees with an active monitoring session. It records the domain, start/end time, duration, work state, category, and policy action. It intentionally does not store full URLs, query strings, page contents, keystrokes, webcam, or microphone data.

## Agent contract

The desktop monitoring agent sends website segments through:

POST /api/monitoring/activity/batch

Each segment may include:

    {
      "domain": "youtube.com",
      "context": "training",
      "started_at": "2026-08-27 09:30:00",
      "ended_at": "2026-08-27 09:35:00",
      "duration_seconds": 300,
      "client_segment_id": "device-segment-123"
    }

The response includes site_controls with the matched category, action, threshold, work state, and distraction-event ID. Login and policy responses also include site_rules and approved employee exceptions so the agent can warn or block locally without waiting for a report upload.

Blocking is enforced by the company-managed desktop/browser agent. The PHP application can classify and audit events, but it cannot see or block browser tabs on its own.

## Configuration examples

- youtube.com → category distracting, action warn, threshold 300 seconds
- youtube.com → category blocked, action block, threshold 0 seconds
- youtube.com with context training → category productive, action allow
- youtube.com with context research → category neutral, action allow

Employees can request a training/research exception. Managers can approve or reject it, and the approval is returned to the agent as an employee-specific exception.

## Scheduled collection

Assign a schedule to each employee. Domain events outside the assigned work window or inside a configured break are not stored. If no schedule is assigned, events are accepted with unscheduled work state so existing installations continue to function; assign schedules to enforce strict work-hour collection.

All manager website-history, report, dispute, rules, and monitoring dashboard reads are written to monitoring_access_logs, including actor, employee/resource scope, filters, IP address, and user agent.
