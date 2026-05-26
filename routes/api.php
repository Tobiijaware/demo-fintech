<?php

use App\Http\Controllers\Api\Admin\AgentSimulatorController;
use App\Http\Controllers\Api\Admin\AgentAnalyticsController;
use App\Http\Controllers\Api\Admin\AgentCommissionController;
use App\Http\Controllers\Api\Admin\AgentController;
use App\Http\Controllers\Api\Admin\AgentTerminalController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\ApprovalRequestController;
use App\Http\Controllers\Api\Admin\MakerCheckerPolicyController;
use App\Http\Controllers\Api\Admin\BackofficeUserController;
use App\Http\Controllers\Api\Admin\OnboardingIdentityController;
use App\Http\Controllers\Api\Admin\OnboardingApplicationController;
use App\Http\Controllers\Api\Admin\TierDefinitionController;
use App\Http\Controllers\Api\Admin\OnboardingDocumentController;
use App\Http\Controllers\Api\Admin\OperationsController;
use App\Http\Controllers\Api\Admin\OperationsIncidentController;
use App\Http\Controllers\Api\Admin\PartnerBankController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\SettlementController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\Admin\CustomerLookupController;
use App\Http\Controllers\Api\Admin\DisputeController;
use App\Http\Controllers\Api\Admin\ReversalRequestController;
use App\Http\Controllers\Api\Admin\SupportTicketController;
use App\Http\Controllers\Api\Admin\ComplianceController;
use App\Http\Controllers\Api\Admin\TreasuryController;
use App\Http\Controllers\Api\Admin\AmlAlertController;
use App\Http\Controllers\Api\Admin\AmlCaseController;
use App\Http\Controllers\Api\Admin\AmlDashboardController;
use App\Http\Controllers\Api\Admin\AmlSanctionsController;
use App\Http\Controllers\Api\Admin\StrFilingController;
use App\Http\Controllers\Api\Admin\WalletFreezeController;
use App\Http\Controllers\Api\Admin\SessionController;
use App\Http\Controllers\Api\Admin\SearchController;
use App\Http\Controllers\Api\Admin\SystemSettingsController;
use App\Http\Controllers\Api\Admin\ProvisioningRequestController;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::prefix('register')->group(function () {
            Route::post('email', [RegistrationController::class, 'sendCode']);
            Route::post('resend-code', [RegistrationController::class, 'resendCode']);
            Route::post('verify-email', [RegistrationController::class, 'verifyEmail']);
            Route::get('criteria', [RegistrationController::class, 'criteria']);
            Route::post('profile', [RegistrationController::class, 'saveProfile']);
            Route::post('validate-bvn', [RegistrationController::class, 'validateBvn']);
            Route::post('complete', [RegistrationController::class, 'complete']);
        });

        Route::post('login', [AuthController::class, 'login']);

        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('setup-pin', [AuthController::class, 'setupPin']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('wallet/balance', [WalletController::class, 'balance']);
        Route::post('wallet/resolve', [TransactionController::class, 'resolve']);
        Route::post('wallet/transfer', [TransactionController::class, 'transfer']);
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{reference}', [TransactionController::class, 'show']);
        Route::get('banks', [BankController::class, 'index']);
        Route::post('banks/resolve', [BankController::class, 'resolve']);

        Route::prefix('kyc')->group(function () {
            Route::get('tier-requirements', [KycController::class, 'tierRequirements']);
            Route::get('tier-definitions', [KycController::class, 'tierDefinitions']);
            Route::get('progress', [KycController::class, 'progress']);
            Route::post('bvn/validate', [KycController::class, 'validateBvn']);
            Route::post('nin/validate', [KycController::class, 'validateNin']);
            Route::post('fields/{key}', [KycController::class, 'saveField']);
            Route::post('documents', [KycController::class, 'storeDocument']);
            Route::post('submit', [KycController::class, 'submit']);
        });

        Route::prefix('admin')->middleware('backoffice.permission:user_management,read')->group(function () {
            Route::get('permissions', [PermissionController::class, 'index']);

            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store'])->middleware('backoffice.permission:user_management,write');
            Route::get('roles/{role}', [RoleController::class, 'show']);
            Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('backoffice.permission:user_management,write');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('backoffice.permission:user_management,write');

            Route::get('maker-checker-policies', [MakerCheckerPolicyController::class, 'index']);
            Route::post('maker-checker-policies', [MakerCheckerPolicyController::class, 'store'])->middleware('backoffice.permission:user_management,write');
            Route::get('maker-checker-policies/{makerCheckerPolicy}', [MakerCheckerPolicyController::class, 'show']);
            Route::put('maker-checker-policies/{makerCheckerPolicy}', [MakerCheckerPolicyController::class, 'update'])->middleware('backoffice.permission:user_management,write');
            Route::delete('maker-checker-policies/{makerCheckerPolicy}', [MakerCheckerPolicyController::class, 'destroy'])->middleware('backoffice.permission:user_management,write');

            Route::get('approval-requests', [ApprovalRequestController::class, 'index']);
            Route::get('approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'show']);
            Route::post('approval-requests/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])->middleware('backoffice.permission:user_management,write');
            Route::post('approval-requests/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])->middleware('backoffice.permission:user_management,write');

            Route::get('users', [BackofficeUserController::class, 'index']);
            Route::post('users', [BackofficeUserController::class, 'store'])->middleware('backoffice.permission:user_management,write');
            Route::put('users/{user}', [BackofficeUserController::class, 'update'])->middleware('backoffice.permission:user_management,write');
            Route::delete('users/{user}', [BackofficeUserController::class, 'destroy'])->middleware('backoffice.permission:user_management,write');
        });

        Route::prefix('admin/transactions')->middleware('backoffice.permission:transactions,read')->group(function () {
            Route::get('stats', [AdminTransactionController::class, 'stats']);
            Route::get('export', [AdminTransactionController::class, 'export']);
            Route::get('/', [AdminTransactionController::class, 'index']);
            Route::get('{reference}', [AdminTransactionController::class, 'show']);
            Route::post('{reference}/retry', [AdminTransactionController::class, 'retry'])
                ->middleware('backoffice.permission:transactions,write');
            Route::post('{reference}/resolve', [AdminTransactionController::class, 'resolve'])
                ->middleware('backoffice.permission:transactions,write');
        });

        Route::prefix('admin/operations')->middleware('backoffice.permission:transactions,read')->group(function () {
            Route::get('dashboard', [OperationsController::class, 'dashboard']);
            Route::get('channels', [OperationsController::class, 'channels']);
            Route::get('partners', [OperationsController::class, 'partners']);

            Route::get('incidents/stats', [OperationsIncidentController::class, 'stats']);
            Route::get('incidents', [OperationsIncidentController::class, 'index']);
            Route::post('incidents', [OperationsIncidentController::class, 'store'])
                ->middleware('backoffice.permission:transactions,write');
            Route::get('incidents/{reference}', [OperationsIncidentController::class, 'show']);
            Route::post('incidents/{reference}/events', [OperationsIncidentController::class, 'events'])
                ->middleware('backoffice.permission:transactions,write');
            Route::post('incidents/{reference}/resolve', [OperationsIncidentController::class, 'resolve'])
                ->middleware('backoffice.permission:transactions,write');
        });

        Route::prefix('admin/onboarding')->middleware('backoffice.permission:kyc_applications,read')->group(function () {
            Route::get('stats', [OnboardingApplicationController::class, 'stats']);
            Route::get('tier-definitions', [OnboardingApplicationController::class, 'tierDefinitions']);
            Route::put('tier-definitions/{tierDefinition}', [TierDefinitionController::class, 'update'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('tier-definitions/{tierDefinition}/criteria', [TierDefinitionController::class, 'storeCriterion'])->middleware('backoffice.permission:kyc_applications,write');
            Route::put('tier-criteria/{tierCriterion}', [TierDefinitionController::class, 'updateCriterion'])->middleware('backoffice.permission:kyc_applications,write');
            Route::delete('tier-criteria/{tierCriterion}', [TierDefinitionController::class, 'destroyCriterion'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('verify/bvn', [OnboardingIdentityController::class, 'verifyBvn'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('verify/nin', [OnboardingIdentityController::class, 'verifyNin'])->middleware('backoffice.permission:kyc_applications,write');
            Route::get('/', [OnboardingApplicationController::class, 'index']);
            Route::get('{onboardingApplication}', [OnboardingApplicationController::class, 'show']);
            Route::post('/', [OnboardingApplicationController::class, 'store'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/submit', [OnboardingApplicationController::class, 'submit'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/approve', [OnboardingApplicationController::class, 'approve'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/reject', [OnboardingApplicationController::class, 'reject'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/query', [OnboardingApplicationController::class, 'query'])->middleware('backoffice.permission:kyc_applications,write');
            Route::post('{onboardingApplication}/hold', [OnboardingApplicationController::class, 'hold'])->middleware('backoffice.permission:kyc_applications,write');

            Route::get('{onboardingApplication}/documents', [OnboardingDocumentController::class, 'index']);
            Route::post('{onboardingApplication}/documents', [OnboardingDocumentController::class, 'store'])->middleware('backoffice.permission:kyc_applications,write');
        });

        Route::prefix('admin/onboarding/documents')->middleware('backoffice.permission:kyc_applications,read')->group(function () {
            Route::get('{onboardingDocument}/file', [OnboardingDocumentController::class, 'show']);
            Route::delete('{onboardingDocument}', [OnboardingDocumentController::class, 'destroy'])->middleware('backoffice.permission:kyc_applications,write');
        });

        Route::prefix('admin/agents')->middleware('backoffice.permission:agent_records,read')->group(function () {
            Route::get('stats', [AgentController::class, 'stats']);
            Route::get('performance', [AgentAnalyticsController::class, 'performance']);
            Route::get('regions', [AgentAnalyticsController::class, 'regions']);
            Route::get('commissions', [AgentCommissionController::class, 'index']);
            Route::get('terminals', [AgentTerminalController::class, 'index']);
            Route::put('terminals/{terminal}', [AgentTerminalController::class, 'update'])->middleware('backoffice.permission:agent_records,write');
            Route::delete('terminals/{terminal}', [AgentTerminalController::class, 'destroy'])->middleware('backoffice.permission:agent_records,write');
            Route::post('{agent}/terminals', [AgentTerminalController::class, 'store'])->middleware('backoffice.permission:agent_records,write');
            Route::post('simulate/resolve', [AgentSimulatorController::class, 'resolve']);
            Route::get('{agent}/simulate/context', [AgentSimulatorController::class, 'context']);
            Route::post('{agent}/simulate/cash-in', [AgentSimulatorController::class, 'cashIn'])
                ->middleware('backoffice.permission.any:agent_records,write,user_management,write');
            Route::post('{agent}/simulate/cash-out', [AgentSimulatorController::class, 'cashOut'])
                ->middleware('backoffice.permission.any:agent_records,write,user_management,write');
            Route::post('{agent}/simulate/wallet-transfer', [AgentSimulatorController::class, 'walletTransfer'])
                ->middleware('backoffice.permission.any:agent_records,write,user_management,write');
            Route::get('/', [AgentController::class, 'index']);
            Route::get('{agent}', [AgentController::class, 'show']);
            Route::put('{agent}', [AgentController::class, 'update'])->middleware('backoffice.permission:agent_records,write');
            Route::post('{agent}/float-top-up', [AgentController::class, 'topUpFloat'])->middleware('backoffice.permission:agent_records,write');
        });

        Route::prefix('admin/audit-logs')->middleware('backoffice.permission:audit_logs,read')->group(function () {
            Route::get('stats', [AuditLogController::class, 'stats']);
            Route::get('/', [AuditLogController::class, 'index']);
        });

        Route::prefix('admin/support/tickets')->middleware('backoffice.permission:reversals,read')->group(function () {
            Route::get('stats', [SupportTicketController::class, 'stats']);
            Route::get('/', [SupportTicketController::class, 'index']);
            Route::post('/', [SupportTicketController::class, 'store'])->middleware('backoffice.permission:reversals,write');
            Route::get('{supportTicket}', [SupportTicketController::class, 'show']);
            Route::put('{supportTicket}', [SupportTicketController::class, 'update'])->middleware('backoffice.permission:reversals,write');
            Route::post('{supportTicket}/assign', [SupportTicketController::class, 'assign'])->middleware('backoffice.permission:reversals,write');
            Route::post('{supportTicket}/resolve', [SupportTicketController::class, 'resolve'])->middleware('backoffice.permission:reversals,write');
            Route::post('{supportTicket}/notes', [SupportTicketController::class, 'notes'])->middleware('backoffice.permission:reversals,write');
        });

        Route::prefix('admin/reversals')->middleware('backoffice.permission:reversals,read')->group(function () {
            Route::get('stats', [ReversalRequestController::class, 'stats']);
            Route::get('/', [ReversalRequestController::class, 'index']);
            Route::post('/', [ReversalRequestController::class, 'store'])->middleware('backoffice.permission:reversals,write');
            Route::post('{reversalRequest}/approve', [ReversalRequestController::class, 'approve'])->middleware('backoffice.permission:reversals,write');
            Route::post('{reversalRequest}/reject', [ReversalRequestController::class, 'reject'])->middleware('backoffice.permission:reversals,write');
        });

        Route::prefix('admin/disputes')->middleware('backoffice.permission:reversals,read')->group(function () {
            Route::get('stats', [DisputeController::class, 'stats']);
            Route::get('/', [DisputeController::class, 'index']);
            Route::post('/', [DisputeController::class, 'store'])->middleware('backoffice.permission:reversals,write');
            Route::put('{dispute}', [DisputeController::class, 'update'])->middleware('backoffice.permission:reversals,write');
            Route::post('{dispute}/resolve', [DisputeController::class, 'resolve'])->middleware('backoffice.permission:reversals,write');
        });

        Route::get('admin/customers/search', [CustomerLookupController::class, 'search'])
            ->middleware('backoffice.permission:reversals,read');

        Route::prefix('admin/settlement')->middleware('backoffice.permission:settlement_recon,read')->group(function () {
            Route::get('stats', [SettlementController::class, 'stats']);
            Route::get('cycles', [SettlementController::class, 'cycles']);
            Route::get('export', [SettlementController::class, 'export']);
            Route::get('exceptions', [SettlementController::class, 'exceptions']);
            Route::get('exceptions/{reference}', [SettlementController::class, 'showException']);
            Route::get('partners', [PartnerBankController::class, 'index']);
            Route::post('run-eod', [SettlementController::class, 'runEod'])
                ->middleware('backoffice.permission:settlement_recon,write');
            Route::post('exceptions/{reference}/resolve', [SettlementController::class, 'resolveException'])
                ->middleware('backoffice.permission:settlement_recon,write');
            Route::post('exceptions/{reference}/credit', [SettlementController::class, 'creditException'])
                ->middleware('backoffice.permission:settlement_recon,write');
            Route::post('exceptions/{reference}/events', [SettlementController::class, 'addExceptionEvent'])
                ->middleware('backoffice.permission:settlement_recon,write');
        });

        Route::prefix('admin/settlement')->middleware('backoffice.permission:settlement_recon,read')->group(function () {
            Route::get('stats', [SettlementController::class, 'stats']);
            Route::get('cycles', [SettlementController::class, 'cycles']);
            Route::get('exceptions', [SettlementController::class, 'exceptions']);
            Route::get('exceptions/{reference}', [SettlementController::class, 'showException']);
            Route::get('export', [SettlementController::class, 'export']);
            Route::get('partners', [PartnerBankController::class, 'index']);
            Route::post('run-eod', [SettlementController::class, 'runEod'])
                ->middleware('backoffice.permission:settlement_recon,write');
            Route::post('exceptions/{reference}/resolve', [SettlementController::class, 'resolveException'])
                ->middleware('backoffice.permission:settlement_recon,write');
            Route::post('exceptions/{reference}/credit', [SettlementController::class, 'creditException'])
                ->middleware('backoffice.permission:settlement_recon,write');
            Route::post('exceptions/{reference}/events', [SettlementController::class, 'addExceptionEvent'])
                ->middleware('backoffice.permission:settlement_recon,write');
        });

        Route::prefix('admin/compliance')->middleware('backoffice.permission:regulatory_filings,read')->group(function () {
            Route::get('stats', [ComplianceController::class, 'stats']);

            Route::get('filings', [ComplianceController::class, 'indexFilings']);
            Route::post('filings', [ComplianceController::class, 'storeFiling'])
                ->middleware('backoffice.permission:regulatory_filings,write');
            Route::get('filings/{regulatoryFiling}', [ComplianceController::class, 'showFiling']);
            Route::put('filings/{regulatoryFiling}', [ComplianceController::class, 'updateFiling'])
                ->middleware('backoffice.permission:regulatory_filings,write');
            Route::post('filings/{regulatoryFiling}/submit', [ComplianceController::class, 'submitFiling'])
                ->middleware('backoffice.permission:regulatory_filings,write');

            Route::get('regulators', [ComplianceController::class, 'indexRegulators']);
            Route::get('regulators/{code}', [ComplianceController::class, 'showRegulator']);
        });

        Route::prefix('admin/compliance')->middleware('backoffice.permission:audit_logs,read')->group(function () {
            Route::get('audit-findings', [ComplianceController::class, 'indexAuditFindings']);
            Route::get('audit-findings/{complianceAuditFinding}', [ComplianceController::class, 'showAuditFinding']);
            Route::put('audit-findings/{complianceAuditFinding}', [ComplianceController::class, 'updateAuditFinding'])
                ->middleware('backoffice.permission:regulatory_filings,write');
        });

        Route::prefix('admin/compliance')->group(function () {
            Route::get('policies', [ComplianceController::class, 'indexPolicies']);
            Route::get('policies/{reference}', [ComplianceController::class, 'showPolicy']);
            Route::get('policies/{reference}/download', [ComplianceController::class, 'downloadPolicy']);
        });

        Route::prefix('admin/aml')->group(function () {
            Route::get('dashboard/stats', [AmlDashboardController::class, 'stats'])
                ->middleware('backoffice.permission:aml_cases,read');

            Route::get('alerts', [AmlAlertController::class, 'index'])
                ->middleware('backoffice.permission:aml_cases,read');
            Route::get('alerts/{amlAlert}', [AmlAlertController::class, 'show'])
                ->middleware('backoffice.permission:aml_cases,read');
            Route::post('alerts/{amlAlert}/assign', [AmlAlertController::class, 'assign'])
                ->middleware('backoffice.permission:aml_cases,write');
            Route::post('alerts/{amlAlert}/escalate', [AmlAlertController::class, 'escalate'])
                ->middleware('backoffice.permission:aml_cases,write');
            Route::post('alerts/{amlAlert}/close', [AmlAlertController::class, 'close'])
                ->middleware('backoffice.permission:aml_cases,write');
            Route::post('alerts/{amlAlert}/convert-to-case', [AmlAlertController::class, 'convertToCase'])
                ->middleware('backoffice.permission:aml_cases,write');

            Route::get('cases', [AmlCaseController::class, 'index'])
                ->middleware('backoffice.permission:aml_cases,read');
            Route::get('cases/{amlCase}', [AmlCaseController::class, 'show'])
                ->middleware('backoffice.permission:aml_cases,read');
            Route::post('cases/{amlCase}/events', [AmlCaseController::class, 'addEvent'])
                ->middleware('backoffice.permission:aml_cases,write');
            Route::post('cases/{amlCase}/resolve', [AmlCaseController::class, 'resolve'])
                ->middleware('backoffice.permission:aml_cases,write');
            Route::post('cases/{amlCase}/escalate', [AmlCaseController::class, 'escalate'])
                ->middleware('backoffice.permission:aml_cases,write');

            Route::get('sanctions', [AmlSanctionsController::class, 'index'])
                ->middleware('backoffice.permission:sanctions_screening,read');
            Route::get('sanctions/{sanctionsHit}', [AmlSanctionsController::class, 'show'])
                ->middleware('backoffice.permission:sanctions_screening,read');
            Route::post('sanctions/{sanctionsHit}/false-positive', [AmlSanctionsController::class, 'falsePositive'])
                ->middleware('backoffice.permission:sanctions_screening,write');
            Route::post('sanctions/{sanctionsHit}/confirm', [AmlSanctionsController::class, 'confirm'])
                ->middleware('backoffice.permission:sanctions_screening,write');

            Route::get('str', [StrFilingController::class, 'index'])
                ->middleware('backoffice.permission:str_filings,read');
            Route::get('str/{strFiling}', [StrFilingController::class, 'show'])
                ->middleware('backoffice.permission:str_filings,read');
            Route::post('str', [StrFilingController::class, 'store'])
                ->middleware('backoffice.permission:str_filings,write');
            Route::post('str/{strFiling}/submit', [StrFilingController::class, 'submit'])
                ->middleware('backoffice.permission:str_filings,write');
            Route::post('str/{strFiling}/approve', [StrFilingController::class, 'approve'])
                ->middleware('backoffice.permission:str_filings,write');
            Route::post('str/{strFiling}/reject', [StrFilingController::class, 'reject'])
                ->middleware('backoffice.permission:str_filings,write');

            Route::post('wallet-freeze', [WalletFreezeController::class, 'store'])
                ->middleware('backoffice.permission:aml_cases,write');
            Route::delete('wallet-freeze/{walletFreeze}', [WalletFreezeController::class, 'destroy'])
                ->middleware('backoffice.permission:aml_cases,write');
        });

        Route::prefix('admin/treasury')->middleware('backoffice.permission:float_positions,read')->group(function () {
            Route::get('stats', [TreasuryController::class, 'stats']);
            Route::get('float-positions', [TreasuryController::class, 'floatPositions']);
            Route::post('float-positions', [TreasuryController::class, 'storeFloatPosition'])
                ->middleware('backoffice.permission:float_positions,write');
            Route::put('float-positions/{floatPosition}', [TreasuryController::class, 'updateFloatPosition'])
                ->middleware('backoffice.permission:float_positions,write');
            Route::post('float-positions/{floatPosition}/top-up', [TreasuryController::class, 'topUp'])
                ->middleware('backoffice.permission:float_positions,write');

            Route::get('fee-revenue', [TreasuryController::class, 'feeRevenue'])
                ->middleware('backoffice.permission:fee_schedules,read');
            Route::get('fee-schedules', [TreasuryController::class, 'feeSchedules'])
                ->middleware('backoffice.permission:fee_schedules,read');

            Route::get('commissions', [TreasuryController::class, 'commissions'])
                ->middleware('backoffice.permission:commission_payout,read');
            Route::get('pnl', [TreasuryController::class, 'pnl'])
                ->middleware('backoffice.permission:float_positions,read');

            Route::get('approvals', [TreasuryController::class, 'approvals'])
                ->middleware('backoffice.permission:commission_payout,read');
            Route::post('approvals/{approvalRequest}/approve', [TreasuryController::class, 'approve'])
                ->middleware('backoffice.permission:commission_payout,write');
            Route::post('approvals/{approvalRequest}/reject', [TreasuryController::class, 'reject'])
                ->middleware('backoffice.permission:commission_payout,write');
        });

        Route::prefix('admin/settings')->middleware('backoffice.permission:system_config,read')->group(function () {
            Route::get('/', [SystemSettingsController::class, 'index']);
            Route::get('integrations', [SystemSettingsController::class, 'integrations']);
            Route::put('/', [SystemSettingsController::class, 'update'])
                ->middleware('backoffice.permission:system_config,write');
        });

        Route::prefix('admin/provisioning-requests')->middleware('backoffice.permission:user_management,read')->group(function () {
            Route::get('/', [ProvisioningRequestController::class, 'index']);
            Route::get('{provisioningRequest}', [ProvisioningRequestController::class, 'show']);
            Route::post('{provisioningRequest}/approve', [ProvisioningRequestController::class, 'approve'])
                ->middleware('backoffice.permission:user_management,write');
            Route::post('{provisioningRequest}/reject', [ProvisioningRequestController::class, 'reject'])
                ->middleware('backoffice.permission:user_management,write');
            Route::post('{provisioningRequest}/return', [ProvisioningRequestController::class, 'returnForClarification'])
                ->middleware('backoffice.permission:user_management,write');
        });

        Route::get('admin/search', [SearchController::class, 'index'])
            ->middleware('backoffice.permission.any:user_management,read,transactions,read');

        Route::prefix('admin/sessions')->middleware('backoffice.permission:system_config,read')->group(function () {
            Route::get('stats', [SessionController::class, 'stats']);
            Route::get('/', [SessionController::class, 'index']);
            Route::delete('{tokenId}', [SessionController::class, 'destroy'])
                ->middleware('backoffice.permission:system_config,write');
        });
    });
});
