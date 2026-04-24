flowchart TD
    %% Nodos de la primera parte
    1["Filter GetProducts with Benefit-<br>Electricity"]
    2["Ask customer to select the country from<br>dropdown"]
    3["Show the providers"]
    4["Customer selects the provider"]
    5["'GetProviderStatus' API call<br>to check provider liveness"]
    6{"Check response:<br>'IsProcessingTransfers'<br>value"}
    7["Display Text ex: &quot;Selected provider is<br>down at this moment, please try after<br>some time&quot;<br>Note: Text to be finalized by product<br>team"]

    %% Nodos de la nueva sección (Salida True)
    8{"Are there multiple products? If only one<br>product, is Minimum.SendValue ==<br>Maximum.SendValue?"}
    
    %% Rama Yes (Denomination Flow)
    9a1["Show the different denomination(s)"]
    9a2["Customer selects amount"]
    9a3["Customer enters Phone Number<br>Note: Phone Number to be validated<br>against GetProviders API with field<br>&quot;ValidationRegex&quot;<br>Ex: Ikeja Nigeria Electricity: ^2340?([0-<br>9]{10})$"]
    9a4["Customer enters required fields from<br>&quot;SettingDefinitions&quot; (in GetProducts)<br>Note: Use &quot;Description&quot; field as Label<br>Ex: &quot;MeterId&quot;"]
    9a5["Customer to select button (Buy Now or<br>Review Order Summary)"]
    
    %% Rama No (Free Range Flow)
    9b1["Customer enters Phone Number<br>Note: Phone Number to be validated<br>against GetProviders API with field<br>&quot;ValidationRegex&quot;<br>Ex: Ikeja Nigeria Electricity: ^2340?([0-<br>9]{10})$"]
    9b2["Customer enters required fields from<br>&quot;SettingDefinitions&quot; (in GetProducts)<br>Note: Use &quot;Description&quot; field as Label<br>Ex: MeterId"]
    9b3["Customer enters amount between<br>Minimum.SendValue and<br>Maximum.SendValue"]
    9b4["'EstimatePrices' API call with SendValue<br>in body"]
    9b5["Display the text just below &quot;Enter<br>Amount&quot; field: &quot;[Currency + Amount]<br>will be received&quot;"]
    9b6["Customer to select button (Buy Now or<br>Review Order Summary)"]

    %% Nodos finales (Círculos)
    C((C))
    D((D))

    %% Conexiones
    1 --> 2
    2 --> 3
    3 --> 4
    4 --> 5
    5 --> 6
    6 -- "False" --> 7
    6 -- "True" --> 8
    
    %% Conexiones Rama Yes
    8 -- "Yes<br><i>Denomination Flow</i>" --> 9a1
    9a1 --> 9a2
    9a2 --> 9a3
    9a3 --> 9a4
    9a4 --> 9a5
    9a5 --> C
    9a5 --> D

    %% Conexiones Rama No
    8 -- "No<br><i>Free Range Flow</i>" --> 9b1
    9b1 --> 9b2
    9b2 --> 9b3
    9b3 --> 9b4
    9b4 --> 9b5
    9b5 --> 9b6
    9b6 --> C
    9b6 --> D

    %% Estilos de color (Consistente con la imagen)
    style 1 fill:#fcd5b4,stroke:#f8cbad,color:#000000
    style 2 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 3 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 4 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 5 fill:#fcd5b4,stroke:#f8cbad,color:#000000
    style 6 fill:#c9ffff,stroke:#00b0f0,color:#000000
    style 7 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 8 fill:#c9ffff,stroke:#00b0f0,color:#000000
    style 9a1 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9a2 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9a3 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9a4 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9a5 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9b1 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9b2 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9b3 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9b4 fill:#fcd5b4,stroke:#f8cbad,color:#000000
    style 9b5 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style 9b6 fill:#5b88c6,stroke:#4a7ebb,color:#ffffff
    style C fill:#92d050,stroke:#00b050,color:#000000
    style D fill:#92d050,stroke:#00b050,color:#000000