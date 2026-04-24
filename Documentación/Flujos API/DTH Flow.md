flowchart TD
    %% Definición de Nodos principales
    n1["1. Filter GetProducts with Benefit-TV"]
    n2["2. Ask customer to select country from drop down\n(Only display the countries that are applicable)"]
    note2["If possible default based on customer nationality\nonly for India & Philippines. Currently DTH is\napplicable only for India, Nepal and Philippines\ncountries"]
    n3["3. Show the providers"]
    n4["4. Customers select the Provider"]
    n5["5. 'GetProviderStatus' API to be\ncalled to check the provider liveness"]
    n6{"6. Check response:\n'IsProcessingTransfers'\nvalue"}
    n7["7. Display text ex: “Selected provider is down\nat this moment, please try after sometime”\nNote: Text to be finalized by product team"]
    n8{"8. Filter 'getProduct'\nbased on SendValue.\nCheck if value is same\nin both Minimum &\nMaximum section"}

    %% Rama Izquierda (Denomination flow)
    n9a1["9.a.1 Show the different denominations"]
    n9a2["9.a.2 Customer to select Amount"]
    n9a3["9.a.3 Customer to enter Account Number\nNote: Account number to be validated against\ngetProviders API response with field “ValidationRegex”\nEx: Sundirect DTH : “^[1|4|7][1][0-9]{10}$”"]
    n9a4["9.a.4 Customer to select button\n(Buy now or Review order summary)"]
    Ca(("C"))
    Da(("D"))

    %% Rama Derecha (Free range flow)
    n9b1["9.b.1 Customer to enter amount\nbetween AED 5.00 - AED 100.00\n(SendValue -Minimum & Maximum values)"]
    n9b2["9.b.2 EstimatePrices API call\nWith parameter “Desired send value”"]
    n9b3["9.b.3 Display the text just below “Enter Amount”\nfield “INR 298.00 will be received”"]
    n9b4["9.b.4 Ask Customer to enter Account Number\nNote: Account number to be validated against\ngetproviders API resp with field “ValidationRegex”\nEx: Airtel DTH: “^3[0-9]{9}$”"]
    n9b5["9.b.4 Customer to select button\n(Buy now or Review order summary)"]
    Cb(("C"))
    Db(("D"))

    %% Conexiones
    n1 --> n2
    n2 -.- note2
    n2 --> n3
    n3 --> n4
    n4 --> n5
    n5 --> n6
    
    n6 -- "False" --> n7
    n6 -- "True" --> n8

    n8 -- "Yes\n(Denomination flow)" --> n9a1
    n9a1 --> n9a2
    n9a2 --> n9a3
    n9a3 --> n9a4
    n9a4 -- "Buy now" --> Ca
    n9a4 -- "Review order\nsummary" --> Da

    n8 -- "No\n(Free range flow)" --> n9b1
    n9b1 --> n9b2
    n9b2 --> n9b3
    n9b3 --> n9b4
    n9b4 --> n9b5
    n9b5 -- "Buy now" --> Cb
    n9b5 -- "Review order\nsummary" --> Db

    %% Estilos de color (Aproximación para mantener fidelidad visual)
    classDef orange fill:#f4cccc,stroke:#e6b8af,color:#000
    classDef blue fill:#4a86e8,stroke:#1155cc,color:#fff
    classDef diamond fill:#c9daf8,stroke:#6d9eeb,color:#000
    classDef note fill:none,stroke:none,color:#000
    classDef green fill:#93c47d,stroke:#38761d,color:#000
    classDef yellow fill:#ffe599,stroke:#f1c232,color:#000

    class n1,n5 orange
    class n2,n3,n4,n7,n9a1,n9a2,n9a3,n9a4,n9b1,n9b3,n9b4,n9b5 blue
    class n6,n8 diamond
    class note2 note
    class n9b2 yellow
    class Ca,Da,Cb,Db green