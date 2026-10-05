<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SalaryStructure extends Model
{
    protected string $table = 'salary_structures';
    protected array $fillable = [
        'company_id', 'name', 'code', 'description', 'currency',
        'is_default', 'is_active', 'effective_from', 'effective_to', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function getItems(int $structureId): array
    {
        return $this->db->fetchAll(
            'SELECT ssi.*, sc.name AS component_name, sc.code AS component_code,
                    sc.type AS component_type, sc.calculation_type AS default_calculation_type,
                    sc.default_amount, sc.percentage_of, sc.is_taxable, sc.is_statutory
             FROM salary_structure_items ssi
             INNER JOIN salary_components sc ON sc.id = ssi.salary_component_id
             WHERE ssi.salary_structure_id = :sid AND sc.deleted_at IS NULL AND sc.is_active = 1
             ORDER BY ssi.sort_order, sc.sort_order',
            ['sid' => $structureId]
        );
    }

    public function getEmployeeAssignment(int $employeeId, string $asOfDate): ?array
    {
        return $this->db->fetch(
            'SELECT ess.*, ss.name AS structure_name, ss.code AS structure_code
             FROM employee_salary_structures ess
             INNER JOIN salary_structures ss ON ss.id = ess.salary_structure_id
             WHERE ess.employee_id = :eid
               AND ess.effective_from <= :dt
               AND (ess.effective_to IS NULL OR ess.effective_to >= :dt2)
               AND ess.deleted_at IS NULL
             ORDER BY ess.is_current DESC, ess.effective_from DESC
             LIMIT 1',
            ['eid' => $employeeId, 'dt' => $asOfDate, 'dt2' => $asOfDate]
        );
    }

    public function getEmployeeComponents(int $employeeId, string $asOfDate): array
    {
        return $this->db->fetchAll(
            'SELECT esc.*, sc.name AS component_name, sc.code AS component_code,
                    sc.type AS component_type, sc.is_taxable, sc.is_statutory
             FROM employee_salary_components esc
             INNER JOIN salary_components sc ON sc.id = esc.salary_component_id
             WHERE esc.employee_id = :eid
               AND esc.effective_from <= :dt
               AND (esc.effective_to IS NULL OR esc.effective_to >= :dt2)
               AND esc.is_active = 1 AND esc.deleted_at IS NULL
             ORDER BY sc.sort_order',
            ['eid' => $employeeId, 'dt' => $asOfDate, 'dt2' => $asOfDate]
        );
    }
}
