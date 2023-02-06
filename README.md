
### Using Jupyter Notebook and CLI tool for uploading attribute, attribute options and families

- Clone repository to local. Install and run.<code>composer require composer</code>
  
### A: Jupyter Notebook:-

>  1. Enter input file path in ```inputPath``` variable.
>  2. Enter attribute column names in ```req_columns``` variable. (Default required are:-'3M ID','Marketplace Formal Name', 'Marketplace Description', 'Marketplace Description Extended', 'Main Picture ', 'Shipper - Item Quantity').
>  3. Enter output file path in ```ouputFileName``` variable.
>  4. Run the whole script from top cell.
>  5. OutputFile will now be generated at provided location.
  

### B: Terminal commands to generate JSONs and push attribute, attribute options and families.

>  1. To generate JSONs for attribute and families; enter command-
>
>>  ```bin/console app:gen-attrs-json <validInputExcelFilePath> <outputJsonFilePath>```
>
>  2. In order to directly push attributes; add flag --attrPush=1. To push families also; add flag --famPush=1.
>
>>  ```bin/console app:gen-attrs-json <validInputExcelFilePath> <outputJsonFilePath> --attrPush=1 --famPush=1```
>
>  3. To generate JSONs for attribute options and families; enter command-
>
>>  ```bin/console app:gen-attrs-options-json <validInputExcelFilePath> <outputJsonFilePath>```
>
>  4. In order to directly push attribute; add flag --push=1.
>
>>  ```bin/console app:gen-attrs-options-json <validInputExcelFilePath> <outputJsonFilePath> --push=1```