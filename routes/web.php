<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DiocesanStructureController;
use App\Http\Controllers\App\DocumentController;
use App\Http\Controllers\App\CommunicationController;
use App\Http\Controllers\App\IntelligenceController;
use App\Http\Controllers\App\ReferenceDataController;
use App\Http\Controllers\App\ReportingPeriodController;
use App\Http\Controllers\App\RoleController;
use App\Http\Controllers\App\SchoolController;
use App\Http\Controllers\App\SchoolReturnController;
use App\Http\Controllers\App\SystemController;
use App\Http\Controllers\App\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware(['guest','throttle:30,1'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('guest')->group(function(){
 Route::get('/register-school',[\App\Http\Controllers\Auth\SchoolRegistrationController::class,'create'])->name('schools.register');
 Route::post('/register-school',[\App\Http\Controllers\Auth\SchoolRegistrationController::class,'store'])->middleware('throttle:5,1')->name('schools.register.store');
 Route::get('/forgot-password',[\App\Http\Controllers\Auth\PasswordResetController::class,'request'])->name('password.request');
 Route::post('/forgot-password',[\App\Http\Controllers\Auth\PasswordResetController::class,'email'])->middleware('throttle:5,1')->name('password.email');
 Route::get('/reset-password/{token}',[\App\Http\Controllers\Auth\PasswordResetController::class,'edit'])->name('password.reset');
 Route::post('/reset-password',[\App\Http\Controllers\Auth\PasswordResetController::class,'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/details', DashboardController::class)->middleware('can:returns.view')->name('dashboard.details');
    Route::get('/administration/users', [UserController::class, 'index'])
        ->middleware('can:users.view')
        ->name('administration.users.index');
    Route::get('/administration/users/create', [UserController::class, 'create'])
        ->middleware('can:users.manage')
        ->name('administration.users.create');
    Route::post('/administration/users', [UserController::class, 'store'])
        ->middleware('can:users.manage')
        ->name('administration.users.store');
    Route::get('/administration/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('can:users.manage')
        ->name('administration.users.edit');
    Route::put('/administration/users/{user}', [UserController::class, 'update'])
        ->middleware('can:users.manage')
        ->name('administration.users.update');
    Route::get('/administration/roles-permissions', [RoleController::class, 'index'])
        ->middleware('can:roles.view')
        ->name('administration.roles.index');
    Route::get('/administration/reference-data', ReferenceDataController::class)
        ->middleware('can:reference-data.view')
        ->name('reference-data.index');
    Route::get('/administration/parishes', [DiocesanStructureController::class, 'parishes'])->middleware('can:diocesan-structure.view')->name('parishes.index');
    Route::get('/administration/parishes/create', [DiocesanStructureController::class, 'createParish'])->middleware('can:diocesan-structure.manage')->name('parishes.create');
    Route::post('/administration/parishes', [DiocesanStructureController::class, 'storeParish'])->middleware('can:diocesan-structure.manage')->name('parishes.store');
    Route::get('/administration/parishes/{parish}/edit', [DiocesanStructureController::class, 'editParish'])->middleware('can:diocesan-structure.manage')->name('parishes.edit');
    Route::put('/administration/parishes/{parish}', [DiocesanStructureController::class, 'updateParish'])->middleware('can:diocesan-structure.manage')->name('parishes.update');
    Route::get('/administration/diocesan-structure', [DiocesanStructureController::class, 'index'])
        ->middleware('can:diocesan-structure.view')
        ->name('diocesan-structure.index');
    Route::get('/administration/diocesan-structure/parishes/create', [DiocesanStructureController::class, 'createParish'])
        ->middleware('can:diocesan-structure.manage')
        ->name('diocesan-structure.parishes.create');
    Route::post('/administration/diocesan-structure/parishes', [DiocesanStructureController::class, 'storeParish'])
        ->middleware('can:diocesan-structure.manage')
        ->name('diocesan-structure.parishes.store');
    Route::get('/administration/diocesan-structure/parishes/{parish}/edit', [DiocesanStructureController::class, 'editParish'])
        ->middleware('can:diocesan-structure.manage')
        ->name('diocesan-structure.parishes.edit');
    Route::put('/administration/diocesan-structure/parishes/{parish}', [DiocesanStructureController::class, 'updateParish'])
        ->middleware('can:diocesan-structure.manage')
        ->name('diocesan-structure.parishes.update');
    Route::get('/administration/diocesan-structure/assignments/create', [DiocesanStructureController::class, 'createAssignment'])
        ->middleware('can:diocesan-structure.manage')
        ->name('diocesan-structure.assignments.create');
    Route::post('/administration/diocesan-structure/assignments', [DiocesanStructureController::class, 'storeAssignment'])
        ->middleware('can:diocesan-structure.manage')
        ->name('diocesan-structure.assignments.store');
    Route::resource('/administration/schools', SchoolController::class)
        ->middleware(['can:schools.view', \App\Http\Middleware\EnsureAdministrator::class])
        ->names('schools')
        ->except(['destroy']);
    Route::resource('/school-statistics/reporting-periods', ReportingPeriodController::class)
        ->middleware(['can:reporting-periods.view', \App\Http\Middleware\EnsureAdministrator::class])
        ->names('reporting-periods')
        ->only(['index', 'create', 'store', 'show']);
    Route::get('/support',[\App\Http\Controllers\App\SupportController::class,'index'])->name('support.index');
    Route::post('/support',[\App\Http\Controllers\App\SupportController::class,'store'])->middleware('throttle:10,1')->name('support.store');
    Route::post('/support/{ticket}/reply',[\App\Http\Controllers\App\SupportController::class,'reply'])->name('support.reply');
    Route::get('/announcements',[\App\Http\Controllers\App\AnnouncementController::class,'index'])->name('announcements.index');
    Route::get('/announcements/create',[\App\Http\Controllers\App\AnnouncementController::class,'create'])->name('announcements.create');
    Route::post('/announcements/preview',[\App\Http\Controllers\App\AnnouncementController::class,'preview'])->name('announcements.preview');
    Route::post('/announcements/publish',[\App\Http\Controllers\App\AnnouncementController::class,'publish'])->middleware('throttle:10,1')->name('announcements.publish');
    Route::get('/alerts',fn () => redirect()->route('announcements.index'));
    Route::get('/announcements/inbox',[\App\Http\Controllers\App\AlertController::class,'index'])->name('alerts.index');
    Route::get('/administration/schools/{school}/message',[\App\Http\Controllers\App\AlertController::class,'composeSchool'])->name('schools.message');
    Route::post('/administration/schools/{school}/message',[\App\Http\Controllers\App\AlertController::class,'storeSchool'])->middleware('throttle:20,1')->name('schools.message.store');
    Route::post('/alerts',[\App\Http\Controllers\App\AlertController::class,'store'])->middleware('throttle:20,1')->name('alerts.store');
    Route::post('/alerts/{alert}/read',[\App\Http\Controllers\App\AlertController::class,'read'])->name('alerts.read');
    Route::get('/school-statistics/reporting-periods/{reportingPeriod}/edit',[ReportingPeriodController::class,'edit'])->middleware(['can:reporting-periods.manage', \App\Http\Middleware\EnsureAdministrator::class])->name('reporting-periods.edit');
    Route::put('/school-statistics/reporting-periods/{reportingPeriod}',[ReportingPeriodController::class,'update'])->middleware(['can:reporting-periods.manage', \App\Http\Middleware\EnsureAdministrator::class])->name('reporting-periods.update');
    Route::get('/my-school/profile/edit',[\App\Http\Controllers\App\RepresentativeController::class,'editProfile'])->name('representative.profile.edit');
    Route::put('/my-school/profile',[\App\Http\Controllers\App\RepresentativeController::class,'updateProfile'])->name('representative.profile.update');
    Route::get('/my-school/profile', [\App\Http\Controllers\App\RepresentativeController::class,'profile'])->name('representative.profile');
    Route::get('/my-school', [\App\Http\Controllers\App\RepresentativeController::class,'home'])->name('representative.home');
    Route::get('/my-school/returns/{return}/confirm-profile', [\App\Http\Controllers\App\RepresentativeController::class,'confirm'])->name('representative.confirm');
    Route::post('/my-school/returns/{return}/confirm-profile', [\App\Http\Controllers\App\RepresentativeController::class,'saveConfirmation'])->name('representative.confirm.save');
    Route::get('/school-statistics/returns', [SchoolReturnController::class, 'index'])
        ->middleware('can:returns.view')
        ->name('returns.index');
    Route::post('/school-statistics/reporting-periods/{reportingPeriod}/schools/{school}/returns/start', [SchoolReturnController::class, 'start'])
        ->middleware('can:returns.view')
        ->name('returns.start');
    Route::get('/school-statistics/returns/{return}', [SchoolReturnController::class, 'show'])
        ->middleware('can:returns.view')
        ->name('returns.show');
    Route::post('/school-statistics/returns/{return}/transition', [SchoolReturnController::class, 'transition'])
        ->middleware('can:returns.view')
        ->name('returns.transition');
    Route::get('/school-statistics/returns/{return}/download', [\App\Http\Controllers\App\FormCopyController::class,'download'])->middleware(['can:returns.view','throttle:20,1'])->name('returns.copy');
    Route::get('/school-statistics/returns/{return}/statistical-form/{section?}', [\App\Http\Controllers\App\StatisticalFormController::class, 'show'])->middleware('can:returns.view')->name('returns.form.show');
    Route::get('/school-statistics/returns/{return}/introduction', [\App\Http\Controllers\App\StatisticalFormController::class, 'introduction'])->middleware('can:returns.view')->name('returns.form.introduction');
    Route::get('/school-statistics/returns/{return}/questionnaire', [\App\Http\Controllers\App\StatisticalFormController::class, 'source'])->middleware('can:returns.view')->name('returns.form.source');
    Route::post('/school-statistics/returns/{return}/draft/{section}', [\App\Http\Controllers\App\StatisticalFormController::class, 'autosave'])->middleware(['can:returns.view', 'throttle:120,1'])->name('returns.form.autosave');
    Route::post('/school-statistics/returns/{return}/statistical-form/{section}', [\App\Http\Controllers\App\StatisticalFormController::class, 'save'])->middleware('can:returns.view')->name('returns.form.save');
    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('can:documents.view')
        ->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])
        ->middleware('can:documents.manage')
        ->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('can:documents.manage')
        ->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('can:documents.view')
        ->name('documents.download');
    Route::get('/notifications', [CommunicationController::class, 'notifications'])
        ->middleware('can:communications.view')
        ->name('notifications.index');
    Route::get('/intelligence/reports', [IntelligenceController::class, 'reports'])
        ->middleware('can:reports.view')
        ->name('reports.index');
    Route::post('/intelligence/reports/preview', [IntelligenceController::class,'preview'])->middleware(['can:reports.export','throttle:20,1'])->name('reports.preview');
    Route::post('/intelligence/reports/export', [IntelligenceController::class, 'export'])
        ->middleware(['can:reports.export','throttle:10,1'])
        ->name('reports.export');
    Route::get('/intelligence/reports/exports/{export}/download', [IntelligenceController::class, 'download'])->middleware('can:reports.export')->name('reports.download');
    Route::get('/intelligence/analytics', [IntelligenceController::class, 'analytics'])
        ->middleware('can:reports.view')
        ->name('analytics.index');
    Route::get('/intelligence/data-quality', [IntelligenceController::class, 'quality'])
        ->middleware('can:reports.view')
        ->name('data-quality.index');
    Route::get('/intelligence/audit-trail', [IntelligenceController::class, 'audit'])
        ->middleware('can:audit.view')
        ->name('audit-trail.index');
    Route::get('/system/production-readiness', [IntelligenceController::class, 'readiness'])
        ->middleware('can:reports.view')
        ->name('production-readiness.index');
    Route::get('/profile', [SystemController::class, 'profile'])->name('profile.show');
    Route::get('/profile/password', [\App\Http\Controllers\App\ChangePasswordController::class,'edit'])->name('profile.password.edit');
    Route::put('/profile/password', [\App\Http\Controllers\App\ChangePasswordController::class,'update'])->middleware('throttle:5,1')->name('profile.password.update');
    Route::get('/system/settings', [SystemController::class, 'settings'])
        ->middleware('can:settings.view')
        ->name('settings.index');
    Route::get('/system/logs', [SystemController::class, 'logs'])
        ->middleware('can:logs.view')
        ->name('logs.index');
});
