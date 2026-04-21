# Frequently Asked Questions â€” DingConnect API

## How do I sell PIN products?

Each product in our API contains a redemption mechanism. This is either â€œImmediateâ€ or â€œReadReceiptâ€. PIN products are of the â€œReadReceiptâ€ type. The information you must provide to your customer will be available in the â€œReceiptTextâ€ which is part of the SendTransfer response.

---

## What is the default account number for PINs?

The default account number for PIN products is 0000000000

---

## I am an existing partner in a previous API version, can I upgrade? What are the implications?

All versions of the API connect to the same system. Your account credentials, products, discounts etc. will remain the same. All your transactions (regardless of the API version) will be included in any report or invoice. Previous API versions may not have the same products supported.

---

## How can I test?

When you are in UAT mode (before going Live) you use the UAT test numbers. When you are in Live mode you can still test with the UAT success numbers you can find in getProducts response (UatNumber field). These numbers always return success and will not deduct anything from your balance.

---

## Can the min and max send values be changed?

Typically, they cannot be changed but certain cases can be made from time to time.

---

## Is there a test account/server?

There are no test accounts. Testing can be accomplished by sending UAT transactions as described above in question 4.

---

## What is the difference between validateOnly:True and validateOnly:False?

ValidateOnly is True it only checks if the syntax are correct and if the user has enough balance, it never deducts from the balance. When it is set to false, money is deducted from the balance and a top up is sent as well as the successful response if no errors occur.

---

## What is the purpose of the parameters named "Settings" and "DistributorRef"?

Some products declare SettingDefintions that mandate name-value pairs that can be submitted with the transfer and will be passed to the Provider. Distributors can submit their own name-value pairs and we will store them with the transfer. These name-value pairs can be queried upon using the ListTransferRecords method. The distributor should also include a DistributorRef that uniquely identifies the transfer within their system.

---

## Whatâ€™s the purpose of â€œBatchItemRefâ€ in the EstimatePrices method?

Each batch request will contain an array of input items that are POSTed in the body of the request. Each of these items must contain a string BatchItemRef property that will uniquely identify the item within the overall request.

---

## Where can I find the transfer types?

TransferType RedemptionMechanism Benefits Notes TopUp Immediate Mobile, Minutes Data Immediate Mobile, Data PIN ReadReceipt Mobile, Minutes, Data Mobile operator products that return a PIN (Can be minutes, data, etc) LDI ReadReceipt LongDistance, Minutes Involves access numbers/calling services (See Pure Minutes) Voucher ReadReceipt Digital Product Products unrelated to mobile operators (See iTunes, Spotify, Google Play, iFlix) Bundle Immediate Mobile, Minutes, Data DTH Immediate TV, Utility Look into DTH India for example

---

## How can we see products and product updates?

It is suggested to run getProducts daily. Depending on sale output and system performance it could be performed hourly or weekly. Automatic email system can be created client side to notify of product changes.

---

## How can I perform various test cases for UAT?

While integrating with our API, your account will be in live mode by default. When you are in Live mode you can still test with the UAT success numbers you can find in getProducts response (UatNumber field). These numbers always return success and will not deduct anything from your balance. To change your account to test mode and perform detailed user acceptance testing as described below your account will need to be configured for test mode by Ding. Please contact partnersupport@ding.com to have test mode enabled. The success case number is available by calling the GetProducts method. It is listed as â€œUatNumberâ€. The other test numbers for the different cases can be assumed from the success number. The only difference is that the final digit of the number. As an example, for Digicel Jamaica, the UatNumber returned from GetProducts is 18760000000. This is the number we will use for the successful case. For the other cases we need to replace the final digit with either 4, 5 or 6 for the other cases like below: 18760000000 â€“ 1, Success 18760000001 â€“ 3, RateLimited 18760000002 â€“ 3, TransientProviderError, ProviderTimedOut 18760000003 â€“ 3, TransientProviderError, ProviderRefusedRequest 18760000004 â€“ 5, ProviderError, ProductUnavailable 18760000005 â€“ 4, AccountNumberInvalid, ProviderRefusedRequest 18760000006 â€“ 5, ProviderError, ProviderTemporarilyUnavailable 18760000007 â€“ 5, InsufficientBalance

---

## Do I need to have balance for performing UAT?

SendTransfer for UAT numbers would work with zero balance. You can fund your account using Self-Funding (Credit/debit Card or PayPal, if currency is supported) or by funding our bank account. You can contact our BD team for account details.

---

## What are the suggested flows?

There are countless different means of consuming our API depending on your system and requirements, the below is just proposed suggestions. Customer manually selects country, provider and product In this first recommendation your application will get all the information beforehand. The main idea of this suggestion is that your system has minimal API calls during each transaction. The customer is expected to provide the necessary parameters needed to complete the transfer. Initially (and ideally outside of the transaction flow) your system should request and persist the following: Countries (getCountries) Providers (getProviders) Regions (getRegions) Products (getProducts) With this information available in your system, your application should be able to: Ask the customer to select country Filter providers by that country Ask the customer to select the provider Show regions (only if there is more than one for that provider) Ask the customer to select the region Filter products using country and provider (and region if applicable) Ask customer to provide account number Send transfer with the necessary information In our second recommendation your application will use our lookup functionality to find out the products available to a given account number. The customer is expected to provide the account number upfront and from this your application should be able to show the products available. Initially (and ideally outside of the transaction flow) your system should again request and persist the following: Countries (getCountries) Providers (getProviders) Regions (getRegions) Products (getProducts) With this information available in your system, your application should: Ask customer for account number Call getAccountLookup with the given account number, possible outcomes: Successful (ResultCode 1) Filter your products with country, region and providers Ask customer to select product Send transfer Nearest match (ResultCode 2) â€“ we are not sure about the providers for the account number but we identified the country Filter your providers with country Ask customer to select provider Ask customer to select region (if there is more than one) Filter products by country, region and provider Ask customer to select product Send transfer Failure (ResultCode 4) â€“ we donâ€™t recognise the country Ask customer to review the phone number or offer the customer to select country, region and providers manually In our 3rd recommendation your application will use our getProduct functionality to find out what products the phone number maps to. The customer is expected to provide the account number upfront and from this your application should be able to show the products available. Initially (and ideally outside of the transaction flow) your system should again request and persist the following: Countries (getCountries) Providers (getProviders) Regions (getRegions) Products (getProducts) With this information available in your system, your application should: Ask customer for account number Call getProduct with the given account number, possible outcomes: Successful (ResultCode 1) Filter your products with country, region and providers Ask customer to select product Send transfer Successful (ResultCode 1) â€“ we are not sure about the products for the account number so a list is displayed, ask the customer to Filter your providers with country Ask customer to select provide Ask customer to select region (if there is more than one) Filter products by country, region and provider Ask customer to select product Send transfer Failure (ResultCode 4) â€“ we donâ€™t recognise the number Ask customer to review the phone number or offer the customer to select country, region and providers manually

---

## Who can I contact for help with API integration?

We have an integration team. Please email partnersupport@ding.com to get in contact.

---

## Who should I contact with questions after integration?

Contacting partnersupport@ding.com is the best option for further questions.

---

## Why do I sometimes see invalid cookie in the API response

As our API end point is protected behind DDoS protection service, such as Incapsula, you might receive malformed cookies in order to inspect the way your application handle these cookies, as part of classification process set by the service - as browsers can handle these cookies, and most Bots can\'t. Usually this would mean receiving an invalid Cookie Header in our API response which consists of a multi-line header and invalid chars in this header value field. So, you should thoroughly test your application to make sure that such scenarios are handled by your application.

---

## Where can I find various Error and Context Codes?

You can find those described in https://www.dingconnect.com/en-US/Api/Description

---

## Can I whitelist an IP/DNS for my API Key?

Yes, you can add or remove the IPâ€™s yourself against the API key. You can head to the Developer section( https://www.dingconnect.com/en-US/ActMgmt/Developer ) and under IP whitelisting you can click on Edit and enter the IP/DNS. API key will be locked to the IP addresses/DNS hosts in the box. Addresses should be separated by comma

---

## Where can I find the Logos for the operators?

Logos for the operators are coming in the response for /GetProviders under the field â€œ LogoUrl â€. You can also download Flags and different sizes of logos from https://imagerepo.ding.com/

---

## What is the ideal approach to implement EstimatePrices?

EstimatePrices is used to know the Receive Value for such SKU Codes (operators) where â€œMaximumâ€ â€œReceiveValueâ€ â€œSendValueâ€ is not equal to â€œMinimumâ€ â€œReceiveValueâ€ â€œSendValueâ€ combo. Thus, indicating that operator is allowing a range of Send Values to be sent under the Min-Max range. So, to know exact Receive Value of any Send Value within the range, you can use Estimate Prices before the transaction. One sample request can be [ { "SendValue": 2.5, "SkuCode": "SKUCode", "BatchItemRef": "YourItemRefNumber" } ] where BatchItemRef (string): A unique reference for an item in a batched request. You can find more details on Batching here. https://www.dingconnect.com/en-US/Api/Description#batching

---

## What fields should I map on my front end to make sure Integration is complete?

You should map following fields based on the product you are selling. SendValue from GetProducts/SendTransfer response. This can be marked up depending on what the customer is paying to you. You can include the Currency ISO also. ReceiveValue from GetProducts/SendTransfer response Receive Value Excluding Tax from GetProducts/SendTransfer response. You can choose to display this one only if ReceiveValue is equal to Receive Value Excluding Tax. ReceiveCurrencyIso from GetProducts/SendTransfer response DefaultDisplayText from GetProducts response, if the product is having benefits other than Minutes, Mobile. This is useful for Bundles, Data, Vouchers, Pins ValidityPeriodISO from GetProducts Response if itâ€™s not null. You would find this in ISO format. E.g. P7D means Valid for 7 days. Description Mark Down and Read More mark down from GetProductDescriptions response if itâ€™s not null. This would contain complete description and PIN redemption instructions. ReceiptText from SendTransfer response if itâ€™s not null (This is for PIN and voucher products) TransferRef from SendTransfer response (This is Ding Transaction ID) DistributorRef from SendTransfer response (This is your internal transaction ID) AccountNumber from SendTransfer response (This is receiver MSISDN)

---

## What should I do if response to SendTransfer is taking longer than usual or I didnâ€™t receive any
                response after
                posting a transaction?

Ding processes most of its transactions within seconds. But for exceptional cases, operators take longer than usual to process some transactions. Your system should wait for 90 seconds to let Ding respond to the SendTransfer request. This should be considered in design as the time out setting with Ding Connect API. In case the connection gets dropped at your end, due to packet drop or network connectivity issue, you can query the transaction status using ListTransferRecords. This API method should be implemented in all cases for Querying and Reconciliation. You can use following parameters to check one transaction. { "DistributorRef": "string", "AccountNumber": "string", "Take": 1 }

---

## Where is ListTransferRecords used apart from Querying the Final Status of the transaction.

You can use the ListTransferRecords to create reconciliation reports and save the response in your DB. You can use any data management service to analyze or display the response to your teams in case they want to go back to the reports. If you want to fetch latest 100 transactions, you would just send Take as 100, { "Take": 100 } Ideally you can keep a daily count of the transactions and fetch those using Take parameter. You can also use Skip in the combination to take. You can find more details about ListTransferRecords and Take and Skip here. https://www.dingconnect.com/en-US/Api/Description#paging

---

## What are various Error and Context Codes?

You can read about the Error Codes here, https://www.dingconnect.com/en-US/Api/Description#error-and-context-codes . Also, you can use the API method GetErrorCodeDescriptions

---

## Can I save the Product List on my side?

You can use a static approach (Saving Products and using GetAccountLookup to see Provider Code and displaying the products based on provider code from your DB), or a Dynamic approach (Fetching the products for a MSISDN using GetProducts?accountNumber=) based on your requirement. If you are saving the Products in your DB, make sure that you are updating the product list every day and saving both the responses , GetProducts and GetProductDescriptions so that when you would display the products they would have all the information necessary for the end user to complete the transaction.

---

## Why has my account reached a threshold limit?

You can view method limits here https://www.dingconnect.com/en-US/Api/Description in the Method Usage section.

---

## Which payment methods I can use to self-fund?

Visa, Mastercard, and Paypal. There might be some delays with Paypal and first time credit card usage as we have to manually verify them.

---


