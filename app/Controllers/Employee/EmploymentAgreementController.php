<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\EmploymentAgreementService;

final class EmploymentAgreementController extends Controller
{
    public function show(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new HttpException('Employee profile not found.', 404);
        }
        $service = new EmploymentAgreementService();
        $agreement = $service->pendingForEmployee((int) $employee['id'], (int) $this->auth->id());
        if (!$agreement) {
            $this->redirect('/employee/dashboard');
        }
        $service->markViewed((int) $agreement['id'], (int) $employee['id']);
        $this->view('employee/onboarding/agreement', [
            'title' => 'Employment Agreement',
            'agreement' => $agreement,
            'employee' => $employee,
            'consentText' => EmploymentAgreementService::CONSENT,
            'pageCount' => EmploymentAgreementService::PAGE_COUNT,
        ], 'layouts/employee');
    }

    public function template(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new HttpException('Employee profile not found.', 404);
        }
        $service = new EmploymentAgreementService();
        if (!$service->ensureForEmployee((int) $employee['id'], (int) $this->auth->id())) {
            throw new HttpException('Employment agreement is unavailable.', 404);
        }
        $this->response->file($service->templatePdfPath(), 'application/pdf');
    }

    public function accept(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new HttpException('Employee profile not found.', 404);
        }
        if ((string) $this->request->input('consent') !== '1') {
            Session::flash('error', 'You must confirm that you read and agree to the agreement.');
            $this->redirect('/employee/onboarding/agreement');
        }
        try {
            (new EmploymentAgreementService())->accept(
                (int) $employee['id'],
                (int) $this->auth->id(),
                (string) $this->request->input('signer_name'),
                (string) $this->request->input('signature_data'),
                $this->request->ip(),
                $this->request->userAgent()
            );
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/employee/onboarding/agreement');
        }
        Session::flash('success', 'Your employment agreement was signed and saved in My Documents.');
        $this->redirect('/employee/documents');
    }
}
