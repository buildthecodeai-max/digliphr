-- Optional cleanup: convert blank department/designation/branch codes to NULL
-- so UNIQUE(company_id, code) no longer treats '' as a colliding value.
-- Safe to run more than once.

UPDATE departments SET code = NULL WHERE code = '';
UPDATE designations SET code = NULL WHERE code = '';
UPDATE branches SET code = NULL WHERE code = '';
