<?php

namespace App\Http\Controllers;

use App\Helpers\WebResponses;
use App\Models\Network;
use App\Models\Referral;
use App\Models\SendInvitation;
use App\Models\User;
use App\Models\UserInvitation;
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
                route('howItWorks')
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

        // Sending email
        if (mail($to, $subject, $message, $headers)) {
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
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

        // Create email headers
        $headers .= 'From: ' . $from . "\r\n" .
            'Reply-To: ' . $from . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        // Compose a simple HTML email message
        $message = '<html><body>';
        $message .= '<p style="color:black;font-size:18px;">Hi,</p><br /><br />';
        $message .= `<p style="color:black;font-size:18px;">You have been invited to join `.$name.`'s network. You can join by clicking on the invitation link below.</p><br /><br />`;
        $message .= '<p style="color:black;font-size:18px;">Invitation Link: <a href="'.route('joinByInvite', $username).'">'.route('joinByInvite', $username).'</a></p><br /><br />';
        $message .= '<p style="color:black;font-size:18px;">Regards,</p><br />';
        $message .= '<p style="color:black;font-size:18px;">Team Tha Network</p><br />';
        $message .= '</body></html>';

        // Sending email
//        Mail::send(
//                 'mails.send-invitation-code-mail',
//                 ['code' => 'code'],
//                 function ($message) use ($to) {
//                     $message->to($to)->subject('Invitation Code!');
//                 }
//             );

        if (mail($to, $subject, $message, $headers)) {
            return true;
        } else {
            return false;
        }
    }

    public function sendInvitation(Request $request) {
//        dd($request->username);
        $data = $request->validate([
            'email' => [
                'required',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->whereNull('deleted_at'),
            ]
        ],[
            'email.unique' => 'This user is already registered on the website.'
        ]);

        try {
            //register mail code if necessary
            //

            if (!$this->invitationMailCode($data['email'], 'Tha Network - Invitation Code!', $request->username, $request->name))
                return WebResponses::exception("Email not sent!");

//            $route = route('loginForm', 'send-invite=success');
            session()->put('send-invite', 'success');

            //check for user's network. create new if not created already
            $network_check = Network::where('user_id', Auth::id())->get();
            if(count($network_check) == 0) {
                Network::create([
                    'user_id' => Auth::id()
                ]);
            }

            //Create referral
            Referral::create([
                'user_id' => Auth::id(),
                'email' => $data['email']
            ]);


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
