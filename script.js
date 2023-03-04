const BODY = document.querySelector('body');
const INPUT = document.getElementById('search');
const HINT = document.getElementById('hint');
const FORM = document.getElementById('search-form');
const RESULT = document.getElementById('result');
const RESET = document.getElementById('reset');

INPUT.focus();

INPUT.addEventListener('input', inputSearch);
INPUT.addEventListener('keydown', searchHintsSwitcher);

// Hide results (hint) by clicking elsewhere
BODY.addEventListener('click', function(e) {
    if(e.target.closest('#search-form') === null) hideHint();
})
// Reset input value
RESET.addEventListener('click', function(e) {
    e.preventDefault();
    INPUT.value = "";
    hideHint();
    INPUT.focus();
})

// Searching cities by input value
function inputSearch() {
    let request = this.value.trim();
    if (request !== "") {
        fetch('./php/core.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                request: request,
                action: "inputSearch"
            })
        })
            .then(response => response.json())
            .then((data) => showHint(data));
    }
    else hideHint();
}

function showHint(data) {
    let foundResults = data['results'];
    let out = '';

    if (foundResults.length > 0) {
        const exactCoincidences = [];
        const similarCoincidences = [];
        const request = data['text'];
        const keysArr = (data['keys'].length > 0) ? data['keys'] : [request]; // Relative key words similar or equal to request

        for (let k in foundResults) {
            let cityArr = foundResults[k].city.split(new RegExp(`\\W`)); //Array from result string
            let city = "";
            let isExactRes = false;

            // Comparing each result key word with each request word to outline coincidences
            cityArr.forEach(word => {
                for (let i in keysArr) {
                    let key = keysArr[i];
                    let posA = word.toLowerCase().indexOf(key.toLowerCase());
                    if (posA == 0) {
                        word = '<b>' + word.slice(posA, key.length) + '</b>' + word.slice(key.length);
                        if (key === request) isExactRes = true;
                        break;
                    }
                }
                city += word + " ";
            })

            let iso = foundResults[k].iso3;
            let name = foundResults[k].city;
            let id = foundResults[k].id;

            let hinItem = `<li id="${id}" name="${name}" class="hint-item">${city} <em>(${iso})</em></li>`;
            (!isExactRes) ? similarCoincidences.push(hinItem) : exactCoincidences.push(hinItem);
        }

        if (exactCoincidences.length > 0) exactCoincidences.forEach(item => out += item);
        if (similarCoincidences.length > 0) {
            if (exactCoincidences.length > 0) out += '<li class="hint-info"><em>Similar results</em></li>';
            similarCoincidences.forEach(item => out += item);
        }
    }
    else {
        out += `<li><em>No results. Try another request.</em></li>`;
    }


    HINT.innerHTML = out;
    FORM.classList.add('hint-shown');

    // Load city info after click on search result
    document.querySelectorAll('.hint-item').forEach(item => {
        item.addEventListener('click', function () {
            let cityId = this.getAttribute('id');
            if (cityId !== null) {
                loadCityInfo(cityId);
                INPUT.value = this.getAttribute('name')
            }
        })
    })
}

//Hide search results
function hideHint() {
    FORM.classList.remove('hint-shown');
    selectedItemIndex = 0;
}

// Switching between hints (results) of search
let selectedItemIndex = 0;
function searchHintsSwitcher(event) {
    if (FORM.classList.contains('hint-shown') && (event.keyCode === 40 || event.keyCode === 38)) { //If hint items are shown
        event.preventDefault()
        let hintResults = document.querySelectorAll('.hint-item');
        let selectedItem = document.querySelector('.active')

        if (hintResults.length > 0) {
            let lastResultIndex = hintResults.length - 1 //Last hint item index in the list

            if (event.keyCode === 40) { // Down
                if (selectedItem === null) hintResults[selectedItemIndex].classList.add("active");
                else {
                    hintResults.forEach(item => item.classList.remove('active'))
                    if (selectedItemIndex < lastResultIndex) {
                        selectedItemIndex++
                        hintResults[selectedItemIndex].classList.add("active")
                    } else {
                        selectedItemIndex = 0
                        hintResults[selectedItemIndex].classList.add("active")
                    }
                }
            }
            else if (event.keyCode === 38) { //Up
                if (selectedItem === null) {
                    hintResults[lastResultIndex].classList.add("active")
                    selectedItemIndex = lastResultIndex
                } else {
                    hintResults.forEach(item => item.classList.remove('active'))
                    if (selectedItemIndex > 0) {
                        selectedItemIndex--
                        hintResults[selectedItemIndex].classList.add("active")
                    } else {
                        selectedItemIndex = lastResultIndex
                        hintResults[selectedItemIndex].classList.add("active")
                    }
                }
            }

            // Set selected item into the INPUT value
            this.value = hintResults[selectedItemIndex].getAttribute('name')
        }
    }
    else if (event.keyCode === 13) {
        event.preventDefault();
        if (document.querySelector('.active') !== null) 
            loadCityInfo(document.querySelector('.active').getAttribute('id'));
    }
}

// Load city information using city ID
function loadCityInfo(cityId) {

    fetch('./php/core.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: cityId,
            action: "loadCityInfo"
        })
    })
        .then(response => response.json())
        .then((data) => showCityInfo(data));

}

function showCityInfo(data) {
    hideHint();
    let out = "";

    let status = "";
    if(data['capital'] === "primary") status = "Country's capital";
    else if(data['capital'] === "admin") status = "First-level admin capital";
    else if(data['capital'] === "minor") status = "Lower-level admin capital";
    else  status = "Town";

    out+=`<li>City name: <b>${data['city']} <em>(${data['city_native']})</em></b> </li>`;
    out+=`<li>Status: <b>${status}</b> </li>`;
    out+=`<li>Country: <b>${data['country']}</b> </li>`;
    out+=`<li>Country iso: <b>${data['iso2']} <em>(iso2)</em> ${data['iso3']} <em>(iso3)</em></li></b> `;
    out+=`<li>Population: <b></b> ${data['population']}</li>`;
    out+=`<li>Coordinates: <b></b> ${data['lat']}N ${data['lng']}E</li>`;

    RESULT.innerHTML = out;
    RESULT.style.display = "block";
}