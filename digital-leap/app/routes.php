<?php
declare(strict_types=1);

use Leap\Controllers\AdminController;
use Leap\Controllers\AuthController;
use Leap\Controllers\CoordinatorController;
use Leap\Controllers\DashboardController;
use Leap\Controllers\DivaController;
use Leap\Controllers\DownloadController;
use Leap\Controllers\ExportController;
use Leap\Controllers\ExportPdfController;
use Leap\Controllers\FeedbackController;
use Leap\Controllers\ManagementController;
use Leap\Controllers\MentorController;
use Leap\Controllers\MessagingController;
use Leap\Controllers\NotificationController;
use Leap\Controllers\ProfileController;
use Leap\Controllers\StudentController;
use Leap\Controllers\SupervisorController;
use Leap\Controllers\TokenController;

return [
    // Public
    'GET ' => [AuthController::class, 'landing', null],
    'GET login' => [AuthController::class, 'loginForm', null],
    'POST login' => [AuthController::class, 'login', null],
    'GET register' => [AuthController::class, 'registerForm', null],
    'POST register' => [AuthController::class, 'register', null],
    'POST logout' => [AuthController::class, 'logout', 'auth'],
    'GET forgot' => [AuthController::class, 'forgotForm', null],
    'POST forgot' => [AuthController::class, 'forgot', null],
    'GET reset' => [AuthController::class, 'resetForm', null],
    'POST reset' => [AuthController::class, 'reset', null],
    'GET token/supervisor' => [TokenController::class, 'form', null],
    'POST token/supervisor' => [TokenController::class, 'consume', null],

    // Shared signed-in pages
    'GET dashboard' => [DashboardController::class, 'index', 'dashboard.view'],
    'GET profile' => [ProfileController::class, 'show', 'profile.view'],
    'POST profile' => [ProfileController::class, 'save', 'profile.edit'],
    'POST profile/cv' => [ProfileController::class, 'cv', 'profile.edit'],
    'POST profile/avatar' => [ProfileController::class, 'avatarUpload', 'profile.edit'],
    'POST profile/avatar/remove' => [ProfileController::class, 'avatarRemove', 'profile.edit'],
    'GET avatar' => [ProfileController::class, 'avatar', 'profile.view'],
    'POST feedback' => [FeedbackController::class, 'save', 'auth'],
    'GET notifications' => [NotificationController::class, 'index', 'notification.view'],
    'POST notifications/read' => [NotificationController::class, 'read', 'notification.view'],
    'POST notifications/read-all' => [NotificationController::class, 'readAll', 'notification.view'],
    'GET download' => [DownloadController::class, 'file', 'auth'],
    'GET logbook/template' => [DownloadController::class, 'logbookTemplate', 'auth'],
    'GET career-fair/template' => [DownloadController::class, 'careerFairTemplate', 'auth'],

    // DIVA assistant
    'GET diva' => [DivaController::class, 'index', 'diva.use'],
    'POST diva/ask' => [DivaController::class, 'ask', 'diva.use'],
    'POST diva/archive' => [DivaController::class, 'archive', 'diva.use'],
    'GET diva/source' => [DivaController::class, 'source', 'diva.use'],
    'GET diva/report-check' => [DivaController::class, 'reportCheckForm', 'diva.use'],
    'POST diva/report-check' => [DivaController::class, 'reportCheck', 'diva.use'],

    // Placement messaging
    'GET messages' => [MessagingController::class, 'inbox', 'message.use'],
    'GET messages/thread' => [MessagingController::class, 'thread', 'message.use'],
    'GET messages/poll' => [MessagingController::class, 'poll', 'message.use'],
    'POST messages/start' => [MessagingController::class, 'start', 'message.use'],
    'POST messages/send' => [MessagingController::class, 'send', 'message.use'],
    'POST messages/edit' => [MessagingController::class, 'edit', 'message.use'],
    'POST messages/delete' => [MessagingController::class, 'remove', 'message.use'],
    'POST messages/react' => [MessagingController::class, 'react', 'message.use'],

    // Student
    'GET student/apply' => [StudentController::class, 'preferences', 'preference.view'],
    'GET student/preferences' => [StudentController::class, 'preferences', 'preference.view'],
    'POST student/preferences/add' => [StudentController::class, 'addPreference', 'preference.submit'],
    'POST student/preferences/remove' => [StudentController::class, 'removePreference', 'preference.submit'],
    'POST student/preferences/move' => [StudentController::class, 'movePreference', 'preference.submit'],
    'POST student/preferences/submit' => [StudentController::class, 'submitPreferences', 'preference.submit'],
    'GET student/placement' => [StudentController::class, 'placement', 'placement.view.own'],
    'GET student/logbook' => [StudentController::class, 'logbook', 'logbook.view.own'],
    'GET student/logbook/new' => [StudentController::class, 'entryForm', 'logbook.submit'],
    'POST student/logbook/save' => [StudentController::class, 'entrySave', 'logbook.submit'],
    'POST student/logbook/submit' => [StudentController::class, 'entrySubmit', 'logbook.submit'],
    'GET student/logbook/view' => [StudentController::class, 'entryView', 'logbook.view.own'],
    'POST student/logbook/comment' => [StudentController::class, 'comment', 'logbook.comment'],
    'POST student/logbook/attach' => [StudentController::class, 'attach', 'logbook.submit'],
    'GET student/report' => [StudentController::class, 'report', 'report.submit'],
    'POST student/report/upload' => [StudentController::class, 'reportUpload', 'report.submit'],
    'GET student/career-fair' => [StudentController::class, 'careerFair', 'report.submit'],
    'POST student/career-fair/upload' => [StudentController::class, 'careerFairUpload', 'report.submit'],
    'GET student/self-evaluation' => [StudentController::class, 'selfEvaluation', 'evaluation.submit.self'],
    'POST student/self-evaluation/save' => [StudentController::class, 'selfEvaluationSave', 'evaluation.submit.self'],
    'GET student/results' => [StudentController::class, 'results', 'mark.view.own'],
    'GET student/letter' => [ExportPdfController::class, 'studentLetter', 'placement.view.own'],

    // Host supervisor
    'GET supervisor/queue' => [SupervisorController::class, 'queue', 'logbook.review'],
    'GET supervisor/entry' => [SupervisorController::class, 'entry', 'logbook.review'],
    'POST supervisor/entry/approve' => [SupervisorController::class, 'approve', 'logbook.review'],
    'POST supervisor/entry/return' => [SupervisorController::class, 'returnEntry', 'logbook.review'],
    'POST supervisor/entry/comment' => [SupervisorController::class, 'comment', 'logbook.comment'],
    'GET supervisor/evaluation' => [SupervisorController::class, 'evaluation', 'evaluation.submit.supervisor'],
    'POST supervisor/evaluation/save' => [SupervisorController::class, 'saveEvaluation', 'evaluation.submit.supervisor'],

    // Academic mentor
    'GET mentor/students' => [MentorController::class, 'students', 'placement.view.own'],
    'GET mentor/logbook' => [MentorController::class, 'logbook', 'logbook.review'],
    'GET mentor/entry' => [MentorController::class, 'entry', 'logbook.review'],
    'POST mentor/entry/comment' => [MentorController::class, 'comment', 'logbook.comment'],
    'GET mentor/visits' => [MentorController::class, 'visits', 'visit.view'],
    'GET mentor/visits/form' => [MentorController::class, 'visitForm', 'visit.manage'],
    'POST mentor/visits/save' => [MentorController::class, 'visitSave', 'visit.manage'],
    'POST mentor/visits/complete' => [MentorController::class, 'visitComplete', 'visit.manage'],
    'GET mentor/evaluation' => [MentorController::class, 'evaluation', 'evaluation.submit.mentor'],
    'POST mentor/evaluation/save' => [MentorController::class, 'saveEvaluation', 'evaluation.submit.mentor'],
    'GET mentor/reports' => [MentorController::class, 'reports', 'report.assess'],
    'POST mentor/reports/assess' => [MentorController::class, 'assessReport', 'report.assess'],

    // Coordinator
    'GET coordinator/organisations' => [CoordinatorController::class, 'organisations', 'org.view'],
    'GET coordinator/organisations/form' => [CoordinatorController::class, 'orgForm', 'org.manage'],
    'POST coordinator/organisations/save' => [CoordinatorController::class, 'orgSave', 'org.manage'],
    'GET coordinator/applications' => [CoordinatorController::class, 'applications', 'placement.view.all'],
    'GET coordinator/applications/view' => [CoordinatorController::class, 'applicationView', 'placement.view.all'],
    'GET coordinator/placements' => [CoordinatorController::class, 'placements', 'placement.view.all'],
    'GET coordinator/placements/allocate' => [CoordinatorController::class, 'allocateForm', 'placement.allocate'],
    'POST coordinator/placements/save' => [CoordinatorController::class, 'allocate', 'placement.allocate'],
    'GET coordinator/placements/view' => [CoordinatorController::class, 'placementView', 'placement.view.all'],
    'POST coordinator/placements/approve' => [CoordinatorController::class, 'approve', 'placement.approve'],
    'POST coordinator/placements/status' => [CoordinatorController::class, 'status', 'placement.allocate'],
    'POST coordinator/placements/type' => [CoordinatorController::class, 'type', 'placement.allocate'],
    'GET coordinator/visits' => [CoordinatorController::class, 'visits', 'visit.view'],
    'GET coordinator/grades' => [CoordinatorController::class, 'grades', 'mark.compute'],
    'GET coordinator/grades/view' => [CoordinatorController::class, 'gradeView', 'mark.compute'],
    'POST coordinator/grades/compute' => [CoordinatorController::class, 'compute', 'mark.compute'],
    'POST coordinator/grades/career-fair' => [CoordinatorController::class, 'careerFair', 'mark.compute'],
    'POST coordinator/grades/moderate' => [CoordinatorController::class, 'moderate', 'mark.moderate'],
    'POST coordinator/grades/publish' => [CoordinatorController::class, 'publish', 'mark.publish'],
    'GET coordinator/grades/print' => [ExportPdfController::class, 'gradeSheet', 'mark.compute'],
    'GET coordinator/letter' => [ExportPdfController::class, 'letter', 'placement.view.all'],

    // Management / AQA
    'GET management/kpis' => [ManagementController::class, 'kpis', 'kpi.view'],
    'GET management/export' => [ExportController::class, 'csv', 'export.data'],
    'GET management/print' => [ManagementController::class, 'printView', 'kpi.view'],

    // Administrator
    'GET admin/users' => [AdminController::class, 'users', 'user.manage'],
    'GET admin/users/form' => [AdminController::class, 'userForm', 'user.manage'],
    'POST admin/users/save' => [AdminController::class, 'userSave', 'user.manage'],
    'POST admin/users/toggle' => [AdminController::class, 'toggle', 'user.manage'],
    'POST admin/users/unlock' => [AdminController::class, 'unlock', 'user.manage'],
    'POST admin/users/reset' => [AdminController::class, 'resetPassword', 'user.manage'],
    'GET admin/roles' => [AdminController::class, 'roles', 'role.view'],
    'GET admin/settings' => [AdminController::class, 'settings', 'setting.manage'],
    'POST admin/settings/save' => [AdminController::class, 'settingsSave', 'setting.manage'],
    'GET admin/audit' => [AdminController::class, 'audit', 'audit.view'],
    'GET admin/audit/view' => [AdminController::class, 'auditView', 'audit.view'],
    'GET admin/feedback' => [FeedbackController::class, 'admin', 'audit.view'],
    'GET admin/mail' => [AdminController::class, 'mail', 'mail.manage'],
    'POST admin/mail/resend' => [AdminController::class, 'mailResend', 'mail.manage'],
    'POST admin/mail/process' => [AdminController::class, 'mailProcess', 'mail.manage'],
];
