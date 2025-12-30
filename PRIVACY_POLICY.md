# Privacy Policy for Pref.Tools Vote

**Last updated:** 2025-12-30

## 1. Controller and Contact Information

The data controller for Pref.Tools Vote ("the Service") is:

**Dominik Peters**
Email: mail@dominik-peters.de

The Service is available at: https://pref.tools/vote

## 2. Overview

Pref.Tools Vote is a web application for creating and participating in polls and elections, with a focus on social choice theory and preference voting methods. This privacy policy explains how we collect, use, and protect your personal data when you use our Service.

We are committed to protecting your privacy and complying with the General Data Protection Regulation (GDPR) and other applicable data protection laws.

## 3. Data We Collect

### 3.1 User Account Data

When you create an account, we collect:
- **Name**: To identify you within the Service
- **Email address**: For authentication, password resets, and email verification
- **Password**: Stored as a cryptographic hash (never in plain text)

### 3.2 Poll Creator Data

When you create a poll, we store:
- Poll titles and descriptions
- Question text and descriptions
- Answer options

### 3.3 Voter Response Data

When you submit a response to a poll, we may collect:
- **Your answers**: The choices you make in the poll
- **Voter name**: Only if the poll creator has enabled name collection and you provide one
- **IP address**: For security and abuse prevention (see Section 3.5 for exceptions)
- **Browser information** (user agent): For security purposes

**Exception for Secret Ballot polls**: When a poll is configured as a "Secret Ballot," we do **not** collect your IP address, browser information, or any identifying data. Your vote remains completely anonymous.

### 3.4 Email Invitation Data

If a poll creator sends you an email invitation:
- Your email address is stored to send the invitation and track delivery status
- We record when invitations are sent and used

### 3.5 Log Data

For security and abuse prevention, we maintain logs that may include:
- IP addresses
- Actions performed (login, poll creation, voting, etc.)
- Timestamps

**Automatic anonymization**: IP addresses in logs and responses are automatically deleted after **90 days**.

## 4. How We Use Your Data

We process your personal data for the following purposes:

| Purpose | Legal Basis (GDPR Art. 6) |
|---------|---------------------------|
| Providing the polling service | Legitimate interest (Art. 6(1)(f)) |
| User authentication and account management | Contract performance (Art. 6(1)(b)) |
| Sending email invitations and notifications | Legitimate interest / Consent |
| Security, fraud prevention, and abuse detection | Legitimate interest (Art. 6(1)(f)) |
| Content moderation to prevent harmful content | Legitimate interest (Art. 6(1)(f)) |

## 5. Data Sharing and Third Parties

We share data with the following third-party service providers:

### 5.1 Hosting Provider

**ALL-INKL.COM - Neue Medien Münnich**
Inhaber: René Münnich
Hauptstraße 68, D-02742 Friedersdorf, Germany
Phone: +49 35872 353-10
Email: info@all-inkl.com

All data is stored on servers located in **Germany**. ALL-INKL.COM also provides our email (SMTP) service.

### 5.2 Cloudflare Turnstile (CAPTCHA)

We use Cloudflare Turnstile to protect against automated abuse during registration and poll creation.

- **Provider**: Cloudflare, Inc. (United States)
- **Data shared**: IP address, browser fingerprint data
- **Purpose**: Bot detection and abuse prevention
- **Privacy policy**: https://www.cloudflare.com/privacypolicy/

### 5.3 OpenAI Moderation API

We use OpenAI's Moderation API to prevent the creation of harmful or inappropriate poll content.

- **Provider**: OpenAI, L.L.C. (United States)
- **Data shared**: Poll titles, descriptions, question text, and answer options
- **Purpose**: Content moderation to prevent harmful content
- **Privacy policy**: https://openai.com/policies/privacy-policy

### 5.4 International Data Transfers

Some of our service providers (Cloudflare, OpenAI) are located in the United States. When your data is transferred to the US, it is protected by:
- Standard Contractual Clauses (SCCs) approved by the European Commission
- The service providers' data protection commitments

## 6. Data Retention

| Data Type | Retention Period |
|-----------|------------------|
| User account data | Until you delete your account |
| Poll and response data | Until the poll creator deletes the poll, or you withdraw your response |
| IP addresses and browser info | **90 days**, then automatically anonymized |
| Email invitation records | Until the poll is deleted |
| Action logs | **90 days** for IP addresses, indefinite for anonymized records |

## 7. Your Rights

Under the GDPR, you have the following rights:

### 7.1 Right of Access (Art. 15)
You can view all data we have about you by logging into your account and clicking "View My Data" on your dashboard.

### 7.2 Right to Data Portability (Art. 20)
You can export your data in a machine-readable format (JSON) by clicking "Export My Data" on your dashboard.

### 7.3 Right to Rectification (Art. 16)
You can update your account information at any time through your dashboard.

### 7.4 Right to Erasure (Art. 17)
- **Delete your account**: Available from your dashboard. You can choose to delete all your polls or keep them (unlinked from your account).
- **Withdraw your vote**: You can withdraw your response from any non-secret-ballot poll, which deletes your answers and personal data while preventing re-voting.
- **Delete your poll**: As a poll creator, you can delete your polls at any time.

### 7.5 Right to Object (Art. 21)
You can unsubscribe from email invitations at any time using the link in any invitation email.

### 7.6 Right to Lodge a Complaint
You have the right to lodge a complaint with a supervisory authority. As the controller is based in France, the relevant authority is:

**CNIL (Commission Nationale de l'Informatique et des Libertés)**
3 Place de Fontenoy, TSA 80715
75334 Paris Cedex 07, France
https://www.cnil.fr

## 8. Cookies

We use only essential cookies required for the Service to function:

| Cookie | Purpose | Duration |
|--------|---------|----------|
| Session cookie | User authentication | Session |
| `voter_token_{pollId}` | Links you to your response for editing | 1 year |

We do **not** use tracking cookies or third-party analytics.

## 9. Age Requirement

The Service is intended for users aged **16 years or older**. We do not knowingly collect personal data from children under 16. If you believe a child under 16 has provided us with personal data, please contact us so we can delete it.

## 10. Security

We implement appropriate technical and organizational measures to protect your data, including:
- Encrypted data transmission (HTTPS/TLS)
- Secure password hashing
- Automatic anonymization of IP addresses after 90 days
- Special protections for secret ballot voting (no identifying data collected)

## 11. Changes to This Policy

We may update this privacy policy from time to time. We will notify registered users of significant changes via email. The "Last updated" date at the top indicates when the policy was last revised.

## 12. Contact Us

For any questions about this privacy policy or to exercise your rights, please contact:

**Dominik Peters**
Email: mail@dominik-peters.de
