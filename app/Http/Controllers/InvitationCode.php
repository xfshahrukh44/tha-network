<?php

namespace App\Http\Controllers;

use App\Events\ReferralSent;
use App\Helpers\WebResponses;
use App\Models\Network;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\SendInvitation;
use App\Models\User;
use App\Models\UserInvitation;
use App\Rules\EmailArray;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvitationCode extends Controller
{
    public function showInvitationCodeForm()
    {
        return Inertia::render('Auth/SignUpInvitation');
    }

    public function sendInvitationCode(Request $request)
    {
        $data = $request->validate([
            'email' => [
                'required_if:email,in:send_code_type',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'phone' => [
                'required_if:phone,in:send_code_type',
                'nullable',
                'string',
                'max:255'
            ],
            'send_code_type' => [
                'required',
                'in:email,phone'
            ],
        ],[
            'email.unique' => 'This user is already registered on the website.'
        ]);
        try {
            $code = $this->generateUniqueCode();
            if ($code instanceof \Exception)
                throw $code;

            DB::beginTransaction();
            $sendInvitation = SendInvitation::firstOrNew([
                'email' => $data['email']
            ]);
            $sendInvitation->phone = $data['phone'];
            $sendInvitation->save();

            $sendInvitation->invitation()->forceDelete();
            $sendInvitation->invitation()->create([
                'code' => $code
            ]);
            DB::commit();

            /* Mail::send(
                 'mails.send-invitation-code-mail',
                 ['code' => $code],
                 function ($message) use ($data) {
                     $message->to($data['email'])->subject('Invitation Code!');
                 }
             );*/

            if (!$this->mailCode($data['email'], 'Tha Network - Invitation Code!', $code))
                return WebResponses::exception("Email not send!");

            $route = route('loginForm', 'send-code=success');
            session()->put('send-code', 'success');
            return WebResponses::success(
                'Request submitted successfully!',
                null,
                $route
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return WebResponses::exception($e->getMessage());
        }
    }

    public function verifyCode(Request $request)
    {
        $userInvitation = null;
        $request->validate([
            'code' => [
                'required',
                'string',
                'size:6',
                function ($attribute, $value, $fail) use (&$userInvitation) {
                    if (!$value) return;
                    $userInvitation = UserInvitation::where('code', $value)
                        ->whereDoesntHave('payment')
                        ->whereNull('deleted_at')
                        ->first();
                    if (is_null($userInvitation))
                        $fail("Invalid code!");
                },
            ],
        ]);
        try {
            session()->put('validate-code', $userInvitation->id);

            return WebResponses::success(
                null,
                null,
                //route('howItWorks')
//                route('paymentShow')
                route('work')
            );
        } catch (\Exception $e) {
            return WebResponses::exception($e->getMessage());
        }
    }

    public function generateUniqueCode()
    {
        try {
            do {
                $code = random_int(100000, 999999);
            } while (UserInvitation::where("code", "=", $code)->first());

            return $code;
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * @param $to
     * @param $subject
     * @param $code
     * @return bool
     */
    public function mailCode($to, $subject, $code)
    {
        $from = 'no-reply@tha-network.com';

        // To send HTML mail, the Content-type header must be set
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

        // Create email headers
        $headers .= 'From: ' . $from . "\r\n" .
            'Reply-To: ' . $from . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // Compose a simple HTML email message
        $message = '<html><body>';
        $message .= '<h1 style="color:#f40;">Welcome to Tha Network!</h1>';
        $message .= '<p style="color:black;font-size:18px;">Please open up the link and use the invitation code given below to make an account: </p>';
        $message .= '<br />' . $code . '<br />';
        $message .= 'Link: <a href="'.route('loginForm', ['send-code' => 'success']).'">'.route('loginForm', ['send-code' => 'success']).'</a>';
        $message .= '</body></html>';

        $html = '<html lang="en">
                    <head>
                        <meta charset="UTF-8" />
                        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                        <title>The Network Membership Pays</title>
                    </head>

                    <body style="padding: 0; margin: 0" style="max-width: 1170px; margin: auto">
                        <table style="width: 1140px; margin: 2rem auto; border-spacing: 0">
                            <tr style="margin-bottom: 20px; width: 100%">
                                <a href="#"><img src="logo.png" class="img-fluid" alt="" style="display: block; max-width: 250px; margin: auto" /></a>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <span style="display: block; margin: 20px 0 0; font-size: 18px; color: #000; font-weight: 500; text-align: center">Invitation Code: '.$code.'</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <h6 style="font-size: 25px; margin: 30px 0 30px; text-align: center">Join ThaNetwork Today</h6>
                                    <a href="#" style="display: table; font-size: 22px; color: green; margin: auto">Because Membership Pays</a>
                                    <span style="display: block; font-size: 20px; color: green; margin: 12px 0 0; text-align: center">$$$$$</span>
                                    <img width="398" height="398" src="'.asset('images/notifications/PaymentMade.png').'" class="img-fluid" alt="img" style="display: table; margin: auto" />
                                </td>
                            </tr>

                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <p style="color: #333; margin: 30px 0 15px; line-height: 31px; font-size: 18px; text-align: center">To learn more about ThaNetwork follow us on our Social Media Platforms</p>
                                    <!-- <p style="color: #333; margin: 10px 0; line-height: 26px">
                                        <a href="#">Invitation Link</a>
                                        Invitation Code 12345
                                    </p> -->
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%; text-align: center">
                                    <a href="#" style="display: inline-block; margin: 0 6px">Facebook</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Twitter</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Youtube</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Tiktok</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Instagram</a>
                                </td>
                            </tr>
                        </table>
                    </body>
                </html>';

        // Sending email
        if (mail($to, $subject, $html, $headers)) {
            return true;
        } else {
            return false;
        }
    }

    public function invitationMailCode($to, $subject, $username, $name)
    {
//        dd($username);
        $from = 'no-reply@tha-network.com';

        // To send HTML mail, the Content-type header must be set
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf8' . "\r\n";

        // Create email headers
        $headers .= 'From: ' . $from . "\r\n" .
            'Reply-To: ' . $from . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // Compose a simple HTML email message
//        $message = '<html><body>';
//        $message .= '<p style="color:black;font-size:18px;">Hi,</p><br /><br />';
//        $message .= `<p style="color:black;font-size:18px;">You have been invited to join `.$name ?? $username.`'s network. You can join by clicking on the invitation link below.</p><br /><br />`;
//        $message .= '<p style="color:black;font-size:18px;">Invitation Link: <a href="'.route('joinByInvite', $username).'">'.route('joinByInvite', $username).'</a></p><br /><br />';
//        $message .= '<p style="color:black;font-size:18px;">Regards,</p><br />';
//        $message .= '<p style="color:black;font-size:18px;">Team Tha Network</p><br />';
//        $message .= '</body></html>';

        $html = '<html lang="en">
                    <head>
                        <meta charset="UTF-8" />
                        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                        <title>The Network Membership Pays</title>
                    </head>

                    <body style="padding: 0; margin: 0" style="max-width: 1170px; margin: auto">
                        <table style="width: 1140px; margin: 2rem auto; border-spacing: 0">
                            <tr style="margin-bottom: 20px; width: 100%">
                                <a href="#"><img src="logo.png" class="img-fluid" alt="" style="display: block; max-width: 250px; margin: auto" /></a>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <p style="color: #333; margin: 0 0 30px; line-height: 31px; font-size: 18px; text-align: center">
                                        Welcome to ThaNetwork.org, '.$name.' invited you to join their network. To learn more about your Invitation click the link below or visit
                                        <a href="https://thanetwork.org/login/" target="_blank">www.thanetwork.org</a> and login using the Invitation link below.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <a href="'.route('joinByInvite', $username).'" style="font-size: 23px; color: #000; font-weight: 600; display: table; margin: auto">Invitation Link</a>
                                    <!-- <span style="display: block; margin: 20px 0 0; font-size: 18px; color: #000; font-weight: 500; text-align: center">Invitation Code 12345</span> -->
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <h6 style="font-size: 25px; margin: 30px 0 30px; text-align: center">Join ThaNetwork Today</h6>
                                    <a href="#" style="display: table; font-size: 22px; color: green; margin: auto">Because Membership Pays</a>
                                    <span style="display: block; font-size: 20px; color: green; margin: 12px 0 0; text-align: center">$$$$$</span>
                                    <img width="398" height="398" src="'.asset('images/notifications/PaymentMade.png').'" class="img-fluid" alt="img" style="display: table; margin: auto" />
                                </td>
                            </tr>

                            <tr>
                                <td colspan="3" style="width: 50%">
                                    <p style="color: #333; margin: 30px 0 15px; line-height: 31px; font-size: 18px; text-align: center">To learn more about ThaNetwork follow us on our Social Media Platforms</p>
                                    <!-- <p style="color: #333; margin: 10px 0; line-height: 26px">
                                        <a href="#">Invitation Link</a>
                                        Invitation Code 12345
                                    </p> -->
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="width: 50%; text-align: center">
                                    <a href="#" style="display: inline-block; margin: 0 6px">Facebook</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Twitter</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Youtube</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Tiktok</a>
                                    <a href="#" style="display: inline-block; margin: 0 6px">Instagram</a>
                                </td>
                            </tr>
                        </table>
                    </body>
                </html>';

        if (mail($to, $subject, $html, $headers)) {
            return true;
        } else {
            return false;
        }
    }

    public function sendInvitation(Request $request) {
//        dd($request->all());
        $data = $request->validate([
            'emails' => [
                'required',
                new EmailArray(),
//                'nullable',
//                'string',
//                'email',
//                'max:255',
//                Rule::unique('users')->whereNull('deleted_at'),
            ]
        ]);

        try {
            //register mail code if necessary
            //

            foreach ($request->emails as $email) {
                if (!$this->invitationMailCode($email, 'Tha Network - Invitation Code!', $request->username, $request->name))
                    return WebResponses::exception("Emails not sent!");

                //Create referral
                Referral::create([
                    'user_id' => Auth::id(),
                    'email' => $email
                ]);
            }


//            $route = route('loginForm', 'send-invite=success');
            session()->put('send-invite', 'success');

            //check for user's network. create new if not created already
            $network_check = Network::where('user_id', Auth::id())->get();
            if(count($network_check) == 0) {
                Network::create([
                    'user_id' => Auth::id()
                ]);
            }

            //send referral creation notification
            $string = "Great Job! Your Referral was sent!! Keep up the good work!!! ";
            $notification = Notification::create([
                'user_id' => Auth::id(),
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => Auth::id(),
                'body' => $string,
                'sender_id' => Auth::id()
            ]);

            event(new ReferralSent(Auth::id(), $string, 'App\Models\User', $notification->id, User::with('profile')->find(Auth::id())));


            return WebResponses::success(
                'Request submitted successfully!',
                null
//                $route
            );
        } catch (\Exception $e) {
//            DB::rollBack();
            return WebResponses::exception($e->getMessage());
        }
    }

    public function join(Request $request, $username) {
        try {
            //get inviter
            $inviter = User::where('username', $username)->first();

//        session()->put('validate-code', '123123123');
            session()->put('inviter_id', $inviter->id);

            $code = $this->generateUniqueCode();
            DB::beginTransaction();
            $sendInvitation = SendInvitation::firstOrNew([
                'email' => 'inviter@tha-network.com'
            ]);
            $sendInvitation->save();
            $sendInvitation->invitation()->forceDelete();
            $sendInvitation->invitation()->create([
                'code' => $code
            ]);
            DB::commit();
            session()->put('send-code', 'success');
            $userInvitation = null;
            $userInvitation = UserInvitation::where('code', $code)
                ->whereDoesntHave('payment')
                ->whereNull('deleted_at')
                ->first();
            session()->put('validate-code', $userInvitation->id);

            return Inertia::render('HowItWorks', [
                'inviter' => $inviter
            ]);
        } catch (\ErrorException $e) {
            return WebResponses::exception($e->getMessage());
        }
    }
}
