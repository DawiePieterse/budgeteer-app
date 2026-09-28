@extends('layouts.app')

@section('title', 'Privacy · Budgeteer')

@section('content')
    <h1>Privacy</h1>

    <section class="card">
        <p>Budgeteer is a private household budget app for the members of one household. It is not offered to the public, and only people on the household's list can sign in.</p>

        <h2>What it keeps</h2>
        <p>Your name, email address and Google profile picture from Google sign-in, and the transactions read from the bank statements and bank notification emails you choose to add: dates, amounts, descriptions, accounts, card number endings and the categories you choose.</p>

        <h2>What it does not keep</h2>
        <p>Statement PDFs are read on your own device and never uploaded; PDF passwords are never sent. Email bodies are not kept once the transaction has been read from them. Card and account numbers are kept only as their last four digits.</p>

        <h2>Google data</h2>
        <p>Google sign-in is used only to confirm who you are. If you link Gmail, Budgeteer only reads messages you have labelled for it, only to read bank transactions from them, and never sends, changes or deletes email. Google data is not shared with anyone, not used for advertising and not used to train AI models. Budgeteer's use of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, including the Limited Use requirements.</p>

        <h2>Where it is stored</h2>
        <p>On Afrihost servers in South Africa. The household's members can see all of the household's data; nobody else can.</p>

        <h2>Your choices</h2>
        <p>You can unlink Gmail at any time, from Budgeteer or from your Google account's security settings. Ask the household's administrator to download or delete your data.</p>

        <h2>Contact</h2>
        <p>Dawie Pieterse, <a href="mailto:dawie.pieterse@gmail.com">dawie.pieterse@gmail.com</a></p>
    </section>
@endsection
