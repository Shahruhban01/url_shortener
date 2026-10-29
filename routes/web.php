<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShortUrlController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/short-urls', [ShortUrlController::class, 'index'])
    ->middleware('role:super_admin,admin,member')
    ->name('short-urls.index');
});

// Role-based access control routes
Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/companies/create', function () {
        return view('invitations.create-company');
    })->name('invitations.company.create');

    // Route::get('/team', [TeamController::class, 'index'])
    //   ->name('team.index');

    // Route::get('/invitations', [
    //     InvitationController::class,
    //     'index',
    // ])->name('invitations.index');

    Route::post('/companies/invite-admin', [
        InvitationController::class,
        'storeCompanyInvitation',
    ])->name('invitations.company.store');
});

Route::get('/team', [TeamController::class, 'index'])
    ->middleware(['auth', 'role:super_admin,admin'])
    ->name('team.index');

Route::get('/invitations', [InvitationController::class, 'index'])
    ->middleware(['auth', 'role:super_admin,admin'])
    ->name('invitations.index');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin-area', function () {
        return 'Admin access granted';
    });

    Route::get('/company/invitations/create', function () {
        return view('invitations.create-member');
    })->name('invitations.member.create');

    Route::post('/company/invitations', [
        InvitationController::class,
        'storeCompanyMemberInvitation',
    ])->name('invitations.member.store');
});

    
// Route::middleware(['auth', 'role:member'])
//     ->get('/member-area', function () {
//         return 'Member access granted';
//     });

// Public redirect
Route::get('/{code}', [ShortUrlController::class, 'redirect'])
    ->where('code', '[A-Za-z0-9]{8}')  // ***...
    ->name('short-urls.redirect');

// Create short URLs: Admin and Member only
Route::middleware(['auth', 'role:admin,member'])->group(function () {
    Route::get('/short-urls/create', function () {
        return view('short-urls.create');
    })->name('short-urls.create');

    Route::post('/short-urls', [ShortUrlController::class, 'store'])
        ->name('short-urls.store');
});


// Send invitation to a new Admin iser
Route::get('/invitations/{token}', [
    InvitationController::class,
    'showAcceptanceForm',
])->name('invitations.accept');

Route::middleware(['auth', 'role:super_admin,admin'])->group(function () {
    Route::delete('/invitations/{id}', [
        InvitationController::class,
        'cancel',
    ])->whereNumber('id')->name('invitations.cancel');
});

// Accept invitation for a new Adminn userr
Route::post('/invitations/{token}', [
    InvitationController::class,
    'accept',
])->middleware('throttle:10,1')
  ->name('invitations.accept.store');


require __DIR__.'/auth.php';
