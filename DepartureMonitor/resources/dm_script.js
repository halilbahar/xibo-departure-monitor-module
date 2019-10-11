$(function () {

    //Copy only the valid data to the new Array
    let currentDate = new Date();
    let currenDateWithoutSeconds = new Date();
    currenDateWithoutSeconds.setMinutes(currenDateWithoutSeconds.getMinutes() + minuteLimit, 0, 0);
    let filteredData = [];
    for (let i = 0; i < data.length; i++) {
        if (currenDateWithoutSeconds <= new Date(data[i].arrivalTime)) {
            filteredData.push(data[i]);
        }
    }

    //Generate rows for every entry
    let table = document.getElementById("table-main");
    for (let i = 0; i < filteredData.length; i++) {
        let tr = table.getElementsByTagName("tbody")[0].insertRow(-1);
        tr.classList.add("tr-content");
        let td = [];
        for (let j = 0; j < 6; j++) {
            td[j] = tr.insertCell(j);
        }
        let dataDate = new Date(filteredData[i].arrivalTime);
        let hour = dataDate.getHours();
        let minute = dataDate.getMinutes();

        td[0].innerHTML = hideIcons ? `<div class="div-height"></div>` : `<img src='${getImageSrc(data[i].type)}'>`;
        td[0].classList.add(dataClasses[0]);

        td[1].innerHTML = data[i].number;
        td[1].classList.add(dataClasses[1]);
        if(hideIcons) {
            td[1].classList.add("td-padding-left-3");
        }

        td[2].innerHTML = data[i].from;
        td[2].classList.add(dataClasses[2]);

        td[3].innerHTML = data[i].to;
        td[3].classList.add("td-align-right-padding-3");
        td[3].classList.add(dataClasses[3]);


        td[4].innerHTML = getLeadingZero(hour) + ":" + getLeadingZero(minute);
        td[4].classList.add("td-align-center");
        td[4].classList.add(dataClasses[4]);

        td[5].innerHTML = Math.ceil((dataDate.getTime() - currentDate.getTime()) / 1000 / 60);
        td[5].classList.add("td-align-right-padding-3");
        td[5].classList.add(dataClasses[5]);
    }

    //Set the backgroundcolor of every second row
    colorBackground(table.rows);

    let nextMinuteDate = new Date();
    nextMinuteDate.setMinutes(currentDate.getMinutes() + 1, 0, 0);
    let waitTime = nextMinuteDate.getTime() - currentDate.getTime();
    //Wait for the minute to finish and count down
    setTimeout(() => {
        countDown();
        //Count down every minute if entry has been expired, animate it out
        setInterval(countDown, 1000 * 60);
    }, waitTime);
});

function countDown() {
    let tableRows = document.getElementById("table-main").rows;
    let minuteIndex = 5;
    for (let i = 1; i < tableRows.length; i++) {
        if (parseInt(tableRows[i].cells[minuteIndex].innerHTML) === minuteLimit) {
            $(`#table-main tr:eq(${i})`)
                .children("td")
                .animate({paddingBottom: 0, paddingTop: 0})
                .wrapInner("<div />")
                .children()
                .slideUp(function () {
                    $(this).closest("tr").remove();
                });
        } else {
            tableRows[i].cells[minuteIndex].innerHTML--;
        }
    }
}

function colorBackground(rows) {
    for (let i = 1; i < rows.length; i++) {
        if (i % 2 === 0) {
            rows[i].style.backgroundColor = tbodySecondBackgroundColor;
        }
    }
}

function getImageSrc(type) {
    let src = "";
    switch (type) {
        case "tram":
            src = tram;
            break;
        case "motorbus":
            src = motorbus;
            break;
        case "citybus":
            src = citybus;
            break;
        case "train":
            src = train;
            break;
        case "underground":
            src = underground;
            break;
        default:
            src = "";
    }
    return src;
}

function getLeadingZero(number) {
    return number < 10 ? "0" + number : number;
}
