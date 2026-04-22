graph TD
    n1["1.0<br>Filter getProduct with<br>&quot;RedemptionMechanism-<br>ReadReceipt&quot;"]
    n2["2.1<br>Ask customer to select country<br>from drop down"]
    n3["2.2<br>Filter and segregate the getProducts based on benefit field & selected country<br>• PIN: Mobile,minutes,data<br>• Voucher : Digital products"]
    n4["3.1<br>List the providers to customers<br>against each country<br>• PIN<br>• Voucher"]
    n5["4.1<br>Customers select the provider"]
    n6["5.1<br>Show the product denoms with<br>description (Check if validity is<br>not null)"]
    n7["6.1<br>Customer select the denom"]
    n8["6.2<br>'GetProviderStatus' API<br>to be called to check the<br>provider liveness"]
    n9{"7.0<br>Response:<br>'IsProcessingTransfers'"}
    n10["8.0<br>Display text ex: &quot;Selected provider is<br>down at this moment, please try after<br>sometime&quot;<br>Note: Text to be finalized by product team"]
    n11["9.0<br>Customer to select button (Buy<br>now or Review order summary)"]
    nA(("A"))
    nB(("B"))

    n1 --> n2
    n2 --> n3
    n3 --> n4
    n4 --> n5
    n5 --> n6
    n6 --> n7
    n7 --> n8
    n8 --> n9
    n9 -- "True" --> n11
    n9 -- "False" --> n10
    n11 -- "Buy now" --> nA
    n11 -- "Review order summary" --> nB