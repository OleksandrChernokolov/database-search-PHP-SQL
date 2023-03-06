# Database search algorithm (PHP/SQL)
Smart and fast search algorithm based on PHP. In this case, a database of world cities search using SQL queries is presented. There are some features of the algorithm:
+ Retrieve the 10 most relevant results or similar results if there are no exact matches.
+ The result will be found even if a small error is made in the request or the request is inaccurate.
+ The presence of an array of exception words or service words that will be found last if there are no other results.
+ High search speed. In the country of the hosting provider, the average request speed is 60 - 100 milliseconds. 
  (In this case, there are 43 thousand cities in the database)
  
The code also comes with a function to create keywords from a string, which is target for search. Having keywords column in DB table makes search more efficient, because:
+ Saves time for processing the target string during the search.
+ Creates the possibility of adding new keywords by which the target string can be found without changing it.
+ Creates the possibility of excluding exception words (or service words).

Search interface implemented using JavaScript, HTML, CSS. This demo is made in a minimalistic but functional design with a convenient mobile version. 
The code also provides a JavaScript function for switching between the found results using the cursor.

## Working demo
https://www.search-city-info.online/

## How to use
1. **Using the search**

In this example, the JavaScript file sends a fetch request with the search text and the "action" variable to the core.php file, which calls the necessary function
in the function.php file according to the value in "action". This allows you to put different functions in the function.php file and call them independently.
In this case, 2 functions are used: search as you type and search for information about the city when you click on one of the cities found.
The search function always returns an object in the JavaScript file with the keys: results, text, keys. The "results" key corresponds to the results array, 
"text" - the escaped text of the query is returned, keys - an array with keys that are similar to the query (they are used to highlight the match in the found text).
This way you can use any kind of php requests and have any JavaScript file structure. You need to do: 
+ In function.php connect to the database by entering the hostname, login, password and database name.
+ Specify the correct path to the core.php file, send request: "serach text ", action: "name of the function".
+ Make sure you have keywords column in target table.(possible to search without keywords, but performance will be worse).

2. **Creating keywords**
   
In createKeywords.php you can find a function which slect a string from target column and divide it in single words which are separated by "+". 
There is an array with exception or service words, which can be used to exclude unwanted words from keywords column. How to use the function(an example):
+ Open the createKeywords.php file and connect to the database by entering the hostname, login, password and database name.
+ Create a new column in target table with String type (make sure the length of the column value should be slightly more than target column).
+ Connect createKeywords.php to your project or insert the code from createKeywords.php to your file with .php extention and reload the page.
+ Note that the process may take some time depending on the number of items in the table.
+ Delete createKeywords.php code from your code or disconnect createKeywords.php

### License
MIT © Oleksandr Chernokolov - [BlackPrick](https://github.com/BlackPrick).
