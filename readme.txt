=== Bitcoin payment for Ninja Forms ===

Contributors: coinsnap
Tags: bitcoin, lightning, ninja forms, payments, btcpay
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.7
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin and Lightning in Ninja Forms for payments, donations, registrations, newsletters, CRM leads, file uploads and micropayments.

== Description ==

= Add Bitcoin and Lightning payments to Ninja Forms =

Coinsnap for Ninja Forms adds Bitcoin as a payment option to Ninja Forms.

Use existing Ninja Forms as lightweight Bitcoin checkout experiences for products, donations, bookings, registrations, newsletter sign-ups, CRM lead generation, file uploads, support requests and other paid interactions — without WooCommerce or another shop system.

Payments can be made with **Bitcoin via Lightning or on-chain** using Coinsnap or your own BTCPay Server.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

= Key features =

* Accept **Bitcoin and Lightning payments** in Ninja Forms
* Support for **Lightning and Bitcoin on-chain**
* Add Bitcoin payments to any selected Ninja Form
* Suitable for payment, donation, signup, registration, newsletter, CRM lead-capture and file-upload forms
* Build simple checkout forms without WooCommerce
* Works alongside other payment methods such as Stripe or PayPal
* Support for **Coinsnap** and **BTCPay Server**
* BTCPay Server setup wizard
* Coinsnap action in **Emails & Actions**
* Fixed payment amount or Ninja Forms Total field
* Custom redirect after payment
* Optional **Bitcoin discount**
* Micropayment and anti-spam use cases
* No own Lightning node required when using Coinsnap
* Non-custodial / self-custody payment model
* Optional bank settlement through supported Coinsnap partners
* Open source and free to install

= Typical use cases =

Use Coinsnap for Ninja Forms for:

* donation forms
* product and service payments
* simple checkout forms
* digital downloads
* event registrations
* bookings and deposits
* paid user registrations
* newsletter sign-ups with a fee
* CRM lead qualification
* paid file uploads
* premium contact or support requests
* micropayment-based anti-spam forms
* pay-per-use services

= How it works =

1. Install and activate Ninja Forms.
2. Install and activate Coinsnap for Ninja Forms.
3. Open **Ninja Forms → Settings**.
4. Connect Coinsnap or BTCPay Server.
5. Create or edit a Ninja Form.
6. Open **Emails & Actions**.
7. Add or enable the **Coinsnap** action.
8. Choose a fixed amount or Ninja Forms Total field.
9. Save and publish the form.
10. Make a test Bitcoin payment.

= Coinsnap payment action =

Coinsnap integrates through the **Coinsnap** action in Ninja Forms **Emails & Actions**.

The action can use either:

* a fixed payment amount, or
* a Ninja Forms Total field.

= Coinsnap or BTCPay Server =

The add-on supports:

* **Coinsnap** — accept Bitcoin without operating your own Bitcoin or Lightning payment infrastructure.
* **BTCPay Server** — connect your own self-hosted BTCPay Server.

= Bitcoin discount =

The current Coinsnap Ninja Forms product supports an optional **Bitcoin discount**.

A fixed or percentage-based discount can be used to encourage visitors to choose Bitcoin.

= Micropayments and anti-spam =

Lightning makes small payments practical.

This enables paid newsletter sign-ups, registrations, qualified CRM leads, file-upload fees, premium support requests and small anti-spam charges.

= Settlement =

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be automatically converted and settled to a bank account.

= Fees =

Coinsnap charges a **1% transaction fee**. The fee is handled separately through the merchant's Coinsnap balance and is not deducted from the Bitcoin payment.

= More information =

* Product page: https://coinsnap.io/wp-add-ons/ninja-forms/
* User Guide: https://coinsnap.io/user-guides/ninja-forms-bitcoin-user-guide/
* WordPress Plugin Directory: https://wordpress.org/plugins/coinsnap-for-ninja-forms/
* Demo: https://ninjaforms.coinsnap.org/
* GitHub: https://github.com/Coinsnap/Coinsnap-for-NinjaForms
* Coinsnap Wallet: https://coinsnap.io/wallet/
* Coinsnap API: https://docs.coinsnap.io/
* Coinsnap account: https://app.coinsnap.io/register

== Installation ==

1. Install and activate **Ninja Forms**.
2. In WordPress, go to **Plugins → Add New**.
3. Search for **Bitcoin payment for Ninja Forms**.
4. Install and activate the Coinsnap add-on.
5. Go to **Ninja Forms → Settings** and connect Coinsnap or BTCPay Server.
6. Edit the form you want to monetize.
7. Open **Emails & Actions**.
8. Add or enable the Coinsnap action.
9. Configure the amount and payment behavior.
10. Make a test Bitcoin payment.

== Frequently Asked Questions ==

= Can Ninja Forms accept Bitcoin payments? =

Yes. Coinsnap for Ninja Forms adds Bitcoin and Lightning payments directly to Ninja Forms.

= Can customers pay with both Lightning and Bitcoin on-chain? =

Yes. The integration supports both Lightning and Bitcoin on-chain.

= Can I add Bitcoin payments to any Ninja Form? =

Yes. The current product documentation includes payment, donation, signup, registration, newsletter, CRM lead-capture and file-upload forms.

= Can Bitcoin be offered alongside Stripe or PayPal? =

Yes. Bitcoin can be offered alongside other payment methods or used as a standalone option.

= Can I use my own BTCPay Server? =

Yes. BTCPay Server is supported as an alternative to Coinsnap.

= Can the payment amount come from the form? =

Yes. The Coinsnap action can use either a fixed amount or a Ninja Forms Total field.

= Does the plugin support a Bitcoin discount? =

Yes. The current Coinsnap product documentation includes an optional fixed or percentage-based Bitcoin discount.

= Can I use the plugin for micropayments or anti-spam? =

Yes. Lightning is suitable for low-value paid interactions and small anti-spam fees.

= Do I need WooCommerce? =

No. Ninja Forms can be used as a lightweight Bitcoin checkout without a shop system.

= Do I need my own Lightning node? =

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

= Does Coinsnap hold my Bitcoin? =

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

= Can payments be settled to a bank account? =

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be converted and settled to a bank account.

= What does Coinsnap cost? =

Coinsnap charges a 1% transaction fee.

== Screenshots ==

1. Install Bitcoin payment for Ninja Forms from the WordPress Plugin Directory
2. Ninja Forms global Coinsnap settings
3. Select Coinsnap or BTCPay Server
4. BTCPay Server setup wizard
5. Ninja Forms form builder
6. Add the Coinsnap action in Emails & Actions
7. Configure a fixed amount or Total field
8. Ninja Forms Bitcoin payment form
9. Bitcoin and Lightning payment QR code
10. Successful payment

== Changelog ==

= 1.3.7 :: 2026-05-24 =
* Compatibility with WordPress 7.0 is tested.

= 1.3.6 :: 2026-04-07 =
* Updated Coinsnap Invoice client.
* Updated Coinsnap Store client.
* Compatibility with Ninja Forms 3.14.2 is tested.
* Compatibility with WordPress 6.9.4 is tested.

= 1.3.5 :: 2026-03-06 =
* Updated plugin description.
* Compatibility with Ninja Forms 3.14.1 is tested.

= 1.3.4 :: 2026-02-02 =
* Fixed PHP warnings in server log.
* Compatibility with Ninja Forms 3.14.0 is tested.

= 1.3.3 :: 2025-12-29 =
* Updated PHP file protection.
* Compatibility with Ninja Forms 3.13.3 is tested.

= 1.3.2 :: 2025-12-04 =
* Compatibility with WordPress 6.9 is tested.
* Compatibility with Ninja Forms 3.13.2 is tested.

= 1.3.1 :: 2025-10-12 =
* Added handling for currencies not supported by BTCPay Server.
* Added custom redirect after payment.
* Compatibility with Ninja Forms 3.12.1 is tested.

= 1.3.0 :: 2025-08-18 =
* Added nonce validation to the BTCPay Server setup flow.
* Updated BTCPay Server minimum-payment calculations.
* Updated BTCPay Server events and webhook handling.
* Compatibility with Ninja Forms 3.11.0 is tested.

= 1.2.2 :: 2025-07-11 =
* Improved API-key setup and connection-status handling.
* Compatibility with Ninja Forms 3.10.4 is tested.

= 1.2.1 :: 2025-07-04 =
* Updated Coinsnap WordPress PHP library.
* Compatibility with Ninja Forms 3.10.3 is tested.

= 1.2.0 :: 2025-06-23 =
* Improved BTCPay Server setup callback handling.
* Added webhook data storage and webhook-secret validation.
* Compatibility with Ninja Forms 3.10.2 is tested.

= 1.1.0 :: 2025-05-30 =
* Added BTCPay Server as a Bitcoin payment gateway.
* Added the BTCPay Server setup wizard.
* Added minimum-payment validation.

= 1.0.1 :: 2025-04-29 =
* Fixed a WordPress text-domain loading warning.

= 1.0.0 :: 2025-03-18 =
* Initial release.
