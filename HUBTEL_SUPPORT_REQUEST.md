# Hubtel Support Request Template

## Account Information:
- **POS ID**: 2031233
- **API Key**: yp7GlzW (first 7 chars)
- **Environment**: Production
- **Website**: baroncast.online

## Issue Description:
We are receiving HTTP 403 Forbidden errors when attempting to use the Direct Receive Money API, despite having valid credentials.

## Error Details:
- **Error Code**: 403 Forbidden
- **API Endpoint**: `/merchantaccount/merchants/{posId}/receive/mobilemoney`
- **Authentication**: Working (credentials accepted)
- **Issue**: Account appears to lack permissions for Direct Receive Money service

## Request:
Please enable the **Direct Receive Money** service for our account (POS ID: 2031233) so we can process mobile money payments through the API.

## Technical Details:
- **Integration Type**: Direct API integration
- **Callback URL**: https://baroncast.online/webhooks/hubtel-receive-money-callback.php
- **Use Case**: Online voting platform with mobile money payments
- **Expected Volume**: Medium (educational/community voting)

## Contact Information:
- **Business**: BaronCast Voting Platform
- **Website**: https://baroncast.online
- **Technical Contact**: [Your contact details]

---
**Note**: Our credentials authenticate successfully but we need the Direct Receive Money service activated on our account.
