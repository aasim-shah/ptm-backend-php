@extends('legal.layout')

@section('title', 'Privacy Policy')

@section('placeholder')
    <p>This policy explains how [Company legal name] ("we", "us") handles personal data in the Parent Teacher Mobile app and website. [Data controller details, and the representative / DPO contact if required.]</p>

    <h2>1. Data we process</h2>
    <ul>
        <li><strong>Account details:</strong> name, email address, phone number, password (stored hashed), role and school.</li>
        <li><strong>Pupil information</strong> entered by parents or schools: name, class, attendance, report cards and the parent or guardian relationship.</li>
        <li><strong>Communications:</strong> chat messages and files you send, meeting requests, and video-call records (time and duration).</li>
        <li><strong>Device data:</strong> a push notification token, and basic technical logs for security.</li>
        <li><strong>Payments:</strong> subscription status. Card or PayPal details are handled by the payment provider, not stored by us.</li>
    </ul>

    <h2>2. Why we use it</h2>
    <p>To provide the Service (accounts, messaging, meetings, reports and notifications), to keep it secure, to manage subscriptions, and to meet legal obligations. [Lawful bases to be confirmed by the owner.]</p>

    <h2>3. Who we share it with</h2>
    <p>Your school, and the teachers and parents you communicate with. Service providers that run parts of the Service on our behalf: hosting, push notifications (Google Firebase), video calls (Agora) and payments (PayPal / app stores). We do not sell personal data.</p>

    <h2>4. Retention</h2>
    <p>[Retention periods to be supplied.] When you delete your account, we delete or anonymise your data unless the law requires us to keep it.</p>

    <h2>5. Children</h2>
    <p>Pupil information is provided by parents, guardians and schools. Children do not create accounts themselves. [Age requirements to be confirmed.]</p>

    <h2>6. Your rights</h2>
    <p>Depending on where you live, you can ask to access, correct, delete or export your data, or object to some processing. [How to make a request, and the relevant supervisory authority.]</p>

    <h2>7. Changes</h2>
    <p>We may update this policy. When we do, the app asks you to acknowledge the new version.</p>

    <h2>8. Contact</h2>
    <p>[Privacy contact email address and postal address.]</p>
@endsection
