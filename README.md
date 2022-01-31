# Crime API WordPress Plugin

### Basic Usage
1. Install and activate plugin
2. Drop crime-la.csv into _meta directory of plugin
3. run /crime-api/?get=import to create database table
4. Use below urls to explore data


### Example URLs
```
/crime-api/?get=all&property=crime_code_description
/crime-api/?get=all&property=crime_code_description&page=2
```

#### Crimes in Southwest
```
/crime-api/?get=crimes-in-area&area=southwest
/crime-api/?get=crimes-in-area&area=southwest&page=23
```

#### Address by Crime Type
```
/crime-api/?get=address-by-type&crime_desc=burglary
/crime-api/?get=address-by-type&crime_desc=burglary&page=4
/crime-api/?get=address-by-type&crime_desc=vehicle+stolen
```


### Output

![output image](https://imgur.com/dY85rV9.png)