# %% [markdown]
# ### Using Jupyter Notebook and CLI tool for uploading attribute, attribute options and families
# 
# - Clone repository to local. Install and run.<code>composer require composer</code>
# 
# ### A: Jupyter Notebook:-
# > 1. Enter input file path in ```inputPath``` variable.
# > 2. Enter attribute column names in ```req_columns``` variable. (Default required are:-'3M ID','Marketplace Formal Name', 'Marketplace Description', 'Marketplace Description        Extended', 'Main Picture ', 'Shipper - Item Quantity').
# > 3. Enter output file path in ```ouputFileName``` variable.
# > 4. Run the whole script from top cell.
# > 5. OutputFile will now be generated at provided location.
# 
# ### B: Terminal commands to generate JSONs and push attribute, attribute options and families.
# > 1. To generate JSONs for attribute and families; enter command-
# > 
# >> ```bin/console app:gen-attrs-json <validInputExcelFilePath> <outputJsonFilePath>```
# >
# > 2. In order to directly push attributes; add flag --attrPush=1. To push families also; add flag --famPush=1.
# >
# >> ```bin/console app:gen-attrs-json <validInputExcelFilePath> <outputJsonFilePath> --attrPush=1 --famPush=1```
# >
# > 3. To generate JSONs for attribute options and families; enter command-
# >
# >> ```bin/console app:gen-attrs-options-json <validInputExcelFilePath> <outputJsonFilePath>```
# >
# > 4. In order to directly push attribute; add flag --push=1.
# >
# >> ```bin/console app:gen-attrs-options-json <validInputExcelFilePath> <outputJsonFilePath> --push=1```
# 

# %%
#Importing Libraries
import pandas as pd
import numpy as np
import subprocess as sp

# %% [markdown]
# #### Setup configurations and file paths
# - inputPath :- Input xlsx file path
# - outputFileName :- Output xlsx file path.
# - req_columns :- Required column names from xlsx file. (Enter exact name. Along with leading or trailing spaces)
# - output JSON folder path:- Path to output JSON storage folder. (Two JSONs will be created. One for attributes and family and second for attribute options. Options JSON will have 'Options' in the file name. JSON file name will be taken from out put excel file name.)
# - familyName :- Family name for current file.
# - attrPush :- Flag to determine whether to push attributes directly from here. (To push enter 1 otherwise 0)
# - famPush :- Flag to determine whether to push families directly from here. (To push enter 1 otherwise 0)
# - optionsPush :- Flag to determine whether to push options directly from here. (To push enter 1 otherwise 0)

# %%
# Required variables
inputPath = "/home/soham/Important/Data/3M/Abrasives/Abrasive Sheets & Rolls/Brush Sheets & Rolls.xlsx"
req_columns = ['3M ID','Marketplace Formal Name', 'Marketplace Description', 'Marketplace Description Extended', 'Main Picture ',
                'Shipper - Item Quantity', 'Abrasive Material','Center Hole Diameter (Imperial)','Grit' ]
outputFileName = '/home/soham/Important/Output/3M/Brush Sheets and Rolls.xlsx'
jsonFolder = '/home/soham/Parser/xml_parser/output/JSON/3M/'
familyName = ""
attrPush = 0
famPush = 0
optionsPush = 0

# %%
#Reading excel file
db = pd.read_excel(inputPath, header=None)

# %%
# Retriving required columns
df = pd.DataFrame(db)
df2 = df.drop(df.index[0])
headers = df2.iloc[0:1,:]
arr = []
for h in range(len(headers.columns)):
    arr.append(headers[h][1])
df2 = df2.drop(df2.index[0])
df2.columns = arr
df3 = df2.loc[:,req_columns]
df3.head()

# %%
#Filetering out invalid columns
invalidValues = ['NaN','0.0 NP','Non Pertinent','']
valCols = []
invalCols = []

for col in df3.columns:
    col_data = df3.loc[:,col]
    isValid = True
    for row in col_data:
        if(row in invalidValues or pd.isna(row)):
            isValid = False
        else:
            isValid = True
            break
    if(isValid==False):
        invalCols.append(col)
    else:
        valCols.append(col)

print("Valid cols are")
for i in valCols:
    print(i)

print("\nInvalid cols are")
for i in invalCols:
    print(i)

# %%
# Filtereing out invalid values from remaining columns
valdf = df3.loc[:, valCols]
valdf = valdf.replace(to_replace=['Non Pertinent','0.0 NP'],value=np.nan)
valdf.head()

# %%
updtdColName = dict()
updtdColName = {'Marketplace Formal Name':"Name",
                'Marketplace Description':'Short Description',
                'Marketplace Description Extended':'Description',
                'Main Picture ':'Image',
                'Shipper - Item Quantity':'Shipping Quantity'}
newCols = []
for col in valdf.columns:
    if(col in updtdColName.keys()):
        newCols.append(updtdColName[col].strip())
    else:
        col = col.replace("(Imperial)","")
        newCols.append(col.strip())

print(newCols)
valdf.columns = newCols
customCols = ['Brand', 'Manufacturer Name', 'Product Info','URL Key','Specification(s)']
for c in customCols:
    valdf[c] = ""

# %%
# Final excel structure
valdf.info()

# %%
#Creating excel with required columns
valdf.to_excel(outputFileName,index=None)

# %%
# Creating json files from jsonFolder and outputFile names.
# familyName = outputFileName.split("/")[-1].split(".")[0].title()
jsonFile = jsonFolder + outputFileName.split("/")[-1].split(".")[0].title().replace(" ","")
jsonAttrFile = jsonFile +".json"
jsonOptionsFile = jsonFile + "Options.json"
jsonOptionsFile

# %%
#Executing attribute and options generation and push command from here.
return_list = sp.run(['/home/soham/Parser/xml_parser/bin/console','app:gen-attrs-json',outputFileName, jsonAttrFile, familyName, '--attrPush='+str(attrPush) ,'--famPush='+str(famPush)])
print('Execution done. Return code', return_list.returncode)
return_list = sp.run(['/home/soham/Parser/xml_parser/bin/console','app:gen-attrs-options-json',outputFileName, jsonOptionsFile, '--push='+str(optionsPush)])
print('Execution done. Return code', return_list.returncode)

# %%



