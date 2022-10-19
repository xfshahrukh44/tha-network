<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FriendRequestController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HowItWorks;
use App\Http\Controllers\InvitationCode;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PusherController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*Route::get('/', function () {
    return Inertia::render('Welcome');
});*/

Route::get('/temp', function() {
//    $joined_networks_ids = NetworkMember::where('user_id', \Illuminate\Support\Facades\Auth::id())->pluck('network_id');
//    $joined_networks_owner_ids = Network::whereIn('id', $joined_networks_ids)->pluck('user_id');
//    dd($joined_networks_owner_ids);
});

Route::get('get/redis', function () {
    dd(\Illuminate\Support\Facades\Redis::get('test:key'));
});
Route::get('set/redis', function () {
    dd(\Illuminate\Support\Facades\Redis::set('test:key', 'this test'));
});
Route::get('del/redis', function () {
    dd(\Illuminate\Support\Facades\Redis::del('test:key'));
});

//ADMIN LOGIN
Route::get('/admin/login', function () {
    return view('admin.auth.login');
})->middleware('guest')->name('admin.login');

Route::namespace('App\Http\Controllers\Admin')->prefix('/admin')->middleware('admin')->group(function () {
    //Dashboard
    Route::get('dashboard', 'AdminController@dashboard')->name('dashboard');

    //setting
    Route::match(['get', 'post'], '/settings', 'SettingController@index')->name('settings');

    //goal
    Route::match(['get', 'post'], '/goals', 'GoalController@index')->name('admin.goals');

    // Customer
    Route::resource('customers', 'CustomersController');
    Route::delete('/customers/destroy/{id}', 'CustomersController@destroy')->name('customers.destroy');
});

//Inertia routes
require "auth.php";
Route::group([
    'middleware' => ['auth', 'revalidate', 'closure']
], function () {;
    Route::get('edit-profile', [ProfileController::class, 'edit'])
        ->name('editProfileForm');
    Route::post('edit-profile', [ProfileController::class, 'update'])
        ->name('updateProfile');
    //Monthly payment route
    Route::get('monthly-success-payment', [HowItWorks::class, 'monthlySuccessPayment'])
        ->name('monthlySuccessPayment');
});
Route::group([
    'middleware' => ['auth', 'revalidate', 'suspension', 'closure']
], function () {
    // home page
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // profile routes
    Route::get('profile', [ProfileController::class, 'show'])
        ->name('profile');
    Route::post('profile-image-upload', [ProfileController::class, 'profileImgUpload'])
        ->name('profileImgUpload');
    Route::post('profile-cover-upload', [ProfileController::class, 'profileCoverUpload'])
        ->name('profileCoverUpload');

    // another user profile
    Route::get('profile/{id}', [ProfileController::class, 'userProfile'])
        ->name('userProfile');

    // Post routes
    Route::post('post/create', [PostController::class, 'store'])
        ->name('postCreate');
    Route::delete('post/{id}/delete', [PostController::class, 'destroy'])
        ->name('postDelete');
    Route::post('post/share', [PostController::class, 'sharePost'])
        ->name('sharePost');

    // Post Like
    Route::post('post/like', [PostController::class, 'postLikeToggle'])
        ->name('postLikeToggle');
    // Comment Like
    Route::post('comment/like', [PostController::class, 'commentLikeToggle'])
        ->name('commentLikeToggle');
    // Reply Like
    Route::post('reply/like', [PostController::class, 'replyLikeToggle'])
        ->name('replyLikeToggle');

    // User follow/unfollow
    Route::post('user/follow', [ProfileController::class, 'userFollowToggle'])
        ->name('userFollowToggle');

    // User block/unblock
    Route::post('user/block', [ProfileController::class, 'userBlockToggle'])
        ->name('userBlockToggle');

    // Post comment
    Route::post('post/comment', [PostController::class, 'postCommentStore'])
        ->name('postCommentStore');
    Route::post('post/comment/delete', [PostController::class, 'postCommentDelete'])
        ->name('postCommentDelete');
    // Comment reply
    Route::post('comment/reply', [PostController::class, 'commentReplyStore'])
        ->name('commentReplyStore');

    //invite other users
    Route::post('send-invitation-code', [InvitationCode::class, 'sendInvitation'])
        ->name('sendInvitation');

    // Chat routes
    Route::get('chat', [ChatController::class, 'index'])
        ->name('chatIndex');
    Route::post('chat/message', [ChatController::class, 'chatMessageStore'])
        ->name('chatMessageStore');
    Route::delete('chat/destroy', [ChatController::class, 'chatMessageDestroy'])
        ->name('chatMessageDestroy');

    // Channel routes
    Route::post('channel/create', [ChannelController::class, 'store'])
        ->name('channelStore');
    Route::delete('channel/destroy', [ChannelController::class, 'channelDestroy'])
        ->name('channelDestroy');

    // Notification routes
    Route::post('channel-notification-viewed/{id}', [ChannelController::class, 'viewedNotification'])
        ->name('channelNotificationViewed');
    Route::post('clear-notifications', [NotificationController::class, 'clearNotifications'])
        ->name('clearNotifications');


    // Friend Request routes
    Route::get('send-request/{target_id}', [FriendRequestController::class, 'sendRequest'])
        ->name('sendRequest');
    Route::get('accept-request/{target_id}', [FriendRequestController::class, 'acceptRequest'])
        ->name('acceptRequest');
    Route::get('reject-request/{target_id}', [FriendRequestController::class, 'rejectRequest'])
        ->name('rejectRequest');
    Route::get('unfriend/{target_id}', [FriendRequestController::class, 'unfriend'])
        ->name('unfriend');
    Route::get('block/{target_id}', [FriendRequestController::class, 'block'])
        ->name('block');
    Route::get('unblock/{target_id}', [FriendRequestController::class, 'unblock'])
        ->name('unblock');

    // Close My Account
    Route::post('close-my-account', [ProfileController::class, 'closeMyAccount'])
        ->name('closeMyAccount');
});

Route::get('/work', function () {
    return Inertia::render('HowItWorks');
})->name('work');

Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('/benefits', function () {
    return Inertia::render('Benifits');
})->name('benefits');

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
