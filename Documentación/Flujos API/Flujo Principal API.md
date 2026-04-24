graph TD
    A["Ask Customer to Select the country, Load the flag and
load the country Regex from GetCountries API call."]
    B["Ask customer to enter Phone number with
country code or select from phone book. Check if
the country code is missing , add from the Prefix
field for selected country."]
    C["getProduct API call with parameters (Country
code +phone number)
(GetProducts?accountNumber=)"]
    D{"Check
getProduct API response for
&quot;RedemptionMechanism&quot; field
possible value: &quot;ReadReceipt&quot;,
&quot;Immediate&quot;,"}
    E{"IF Provider's returned are
ReadReceipt (Single or multiple)"}
    F["Redirect to the Pin
Flow"]
    G{"If Providers returned have mix
of ReadReceipt and Immediate
or Multiple &quot;Immediate&quot;"}
    H["Ignore Providers
with ReadReceipt,
show Provider(s)
with Immediate only"]

    Note["Below API's should  be called  every 3 hours in a
day and store in your DB which will push the data
to cache during Ding user  flow
* GetProducts
* GetProductDescriptions

Below API's can be called once in a Day and
stored in your DB as well.
* GetCountries
* GetProviders
* GetRegions
* GetProviderStatus
*GetPromotions
*GetPromotionDescriptions"]

    %% --- NUEVOS NODOS (Parte 1) ---
    I{"Check
'getProduct' API response has
>1 Immediate Provider Codes
?"}
    J["Ask customer
to select
provider"]
    K{"Check
'getProducts' API
response > 1
Region?"}
    L["Ask customer to
select region"]
    N["'GetProviderStatus' API to be called to check the
provider status"]
    O{"Response:
'IsProcessingTransfe
rs'"}
    P["Display text ex: &quot;Selected provider is down at
this moment, please try after sometime&quot;
Note: Text to be finalized by product team"]
    Q{"Check
'getProduct' API
response,if SendValue is
same in both Minimum
& Maximum
section"}
    R(("A"))
    R_lbl["Denomination
flow"]
    S(("B"))
    S_lbl["Free range
flow"]

    %% --- NUEVOS NODOS (Parte 2: Denomination Flow) ---
    A1["Filter GetPromotions data with
CurrencyIso & details from
GetPromotionDescriptions with
LocalizationKey(Primary key)"]
    A2{"Check
&quot;GetPromotions&quot;
response has
data"}
    A3["Show the Bonus Heading and
Details as a pop up option (PF
Screenshot below)"]
    A4["Show below options as Tab and display products
with description to the customer based on the
response fields
• Top Up
• Data
• Bundles"]
    A5["Display Fields against each SKU as
1. DefaultDisplayText + ValidityPeriodIso (if not
null)
2.Send Value
3. ReceiveValue
3. ReadMoreMarkdown from the
GetProductDescription (If not Null)"]
    A6["Customers select the amount (Product)"]
    A7["Customer to select button (Buy now or Review
order summary)"]
    A8C(("C"))
    A8C_lbl["Buy now"]
    A8D(("D"))
    A8D_lbl["Review order
summary"]

    %% --- NUEVOS NODOS (Parte 3: Free Range Flow) ---
    B1["Filter GetPromotions data with
CurrencyIso & details from
GetPromotionDescriptions with
LocalizationKey(Primary key)"]
    B2{"Check
&quot;GetPromotions&quot;
response has
data"}
    B3["Show the Bonus Heading and
Details as a pop up option (PF
Screenshot below)"]
    B4["Show below options as Tab and display
products with description to the
customer based on the response fields
• Recharge (Enter Amount)"]
    B5["Ask Customer to enter the amount
Between AED 5.00 - AED 380.00
(SendValue -Minimum & Maximum
values)"]
    B6["EstimatePrices API call
With parameter send value"]
    B7["Display the Receive Value
(+ReceiveValueExcludingTax) for the
entered amount from the response"]
    B8["Customer to select button (Buy now or
Review order summary)"]
    B9C(("C"))
    B9C_lbl["Buy now"]
    B9D(("D"))
    B9D_lbl["Review order
summary"]

    %% --- LEYENDA (Legend) ---
    L1["Front end App"]
    L2["Backend-Online API call"]
    L3["catalogue call"]
    L4["Backend Process"]

    %% --- CONEXIONES ---
    A --> B
    B --> C
    C --> D
    D --> E
    E --> F
    D --> G
    G --> H

    %% Conexión Parte 1
    H --> I
    I -- Yes --> J
    I -- No --> N
    J --> K
    K -- yes --> L
    K -- no --> N
    L --> N
    N --> O
    O -- False --> P
    O -- True --> Q
    
    Q -- Yes --> R
    R --- R_lbl
    
    Q -- No --> S
    S --- S_lbl

    %% Conexiones Parte 2 (Denomination Flow)
    R --> A1
    A1 --> A2
    A2 -- Yes --> A3
    A2 -- No --> A4
    A3 --> A4
    A4 --> A5
    A5 --> A6
    A6 --> A7
    A7 --> A8C
    A7 --> A8D
    A8C --- A8C_lbl
    A8D --- A8D_lbl

    %% Conexiones Parte 3 (Free Range Flow)
    S --> B1
    B1 --> B2
    B2 -- Yes --> B3
    B2 -- No --> B4
    B3 --> B4
    B4 --> B5
    B5 --> B6
    B6 --> B7
    B7 --> B8
    B8 --> B9C
    B8 --> B9D
    B9C --- B9C_lbl
    B9D --- B9D_lbl

    %% Conexión invisible de la Leyenda para agruparla visualmente
    L1 ~~~ L2
    L2 ~~~ L3
    L3 ~~~ L4

    %% --- ESTILOS ---
    style A fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style B fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style C fill:#fff2cc,color:#000000,stroke:#d6b656,stroke-width:1px
    style D fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style E fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style F fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style G fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style H fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style Note fill:#fce4d6,color:#000000,stroke:#fce4d6,stroke-width:1px

    style I fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style J fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style K fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style L fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style N fill:#f4b183,color:#000000,stroke:#c55a11,stroke-width:1px
    style O fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style P fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style Q fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    
    style R fill:#a9d18e,color:#0000ff,stroke:#548235,stroke-width:1px
    style S fill:#a9d18e,color:#0000ff,stroke:#548235,stroke-width:1px
    style R_lbl fill:none,color:#0000ff,stroke:none,stroke-width:0px
    style S_lbl fill:none,color:#0000ff,stroke:none,stroke-width:0px

    %% Estilos Denomination Flow
    style A1 fill:#f4b183,color:#000000,stroke:#c55a11,stroke-width:1px
    style A2 fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style A3 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style A4 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style A5 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style A6 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style A7 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style A8C fill:#a9d18e,color:#0000ff,stroke:#548235,stroke-width:1px
    style A8D fill:#a9d18e,color:#0000ff,stroke:#548235,stroke-width:1px
    style A8C_lbl fill:none,color:#0000ff,stroke:none,stroke-width:0px
    style A8D_lbl fill:none,color:#0000ff,stroke:none,stroke-width:0px

    %% Estilos Free Range Flow
    style B1 fill:#f4b183,color:#000000,stroke:#c55a11,stroke-width:1px
    style B2 fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px
    style B3 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style B4 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style B5 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style B6 fill:#fff2cc,color:#000000,stroke:#d6b656,stroke-width:1px
    style B7 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style B8 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style B9C fill:#a9d18e,color:#0000ff,stroke:#548235,stroke-width:1px
    style B9D fill:#a9d18e,color:#0000ff,stroke:#548235,stroke-width:1px
    style B9C_lbl fill:none,color:#0000ff,stroke:none,stroke-width:0px
    style B9D_lbl fill:none,color:#0000ff,stroke:none,stroke-width:0px

    %% Estilos Leyenda
    style L1 fill:#4472c4,color:#ffffff,stroke:#2f528f,stroke-width:1px
    style L2 fill:#fff2cc,color:#000000,stroke:#d6b656,stroke-width:1px
    style L3 fill:#f4b183,color:#000000,stroke:#c55a11,stroke-width:1px
    style L4 fill:#e0ffff,color:#000000,stroke:#4472c4,stroke-width:2px