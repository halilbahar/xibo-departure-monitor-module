$(function () {

    //Copy only the valid data to the new Array
    var currentDate = new Date();
    var currenDateWithoutSeconds = new Date();
    currenDateWithoutSeconds.setMinutes(currenDateWithoutSeconds.getMinutes() + minuteLimit, 0, 0);
    var filteredData = [];
    for (var i = 0; i < data.length; i++) {
        if (currenDateWithoutSeconds <= new Date(data[i].arrivalTime)) {
            filteredData.push(data[i]);
        }
    }

    //Generate rows for every entry
    var table = document.getElementById("table-main");
    for (var i = 0; i < filteredData.length; i++) {
        var tr = table.getElementsByTagName("tbody")[0].insertRow(-1);
        tr.classList.add("tr-content");
        var td = [];
        for (var j = 0; j < 6; j++) {
            td[j] = tr.insertCell(j);
        }
        var dataDate = new Date(filteredData[i].arrivalTime);
        var hour = dataDate.getHours();
        var minute = dataDate.getMinutes();

        var content = "";
        if (!hideIcons) {
            content = "<img src='" + getImageSrc(data[i].type) + "'>";
        }
        td[0].innerHTML = "<div class='div-height'>" + content + "</div>";
        td[1].innerHTML = data[i].number;
        td[2].innerHTML = data[i].from;
        td[3].innerHTML = data[i].to;
        td[4].innerHTML = getLeadingZero(hour) + ":" + getLeadingZero(minute);
        td[5].innerHTML = Math.ceil((dataDate.getTime() - currentDate.getTime()) / 1000 / 60);

        if (!disableAnimation) {
            var isEven = (i + 1) % 2 === 0;
            createMarquee(td[2], isEven);
            createMarquee(td[3], isEven);
        }

        //Set the backgroundcolor of every second row
        if ((i + 1) % 2 === 0) {
            tr.style.backgroundColor = tbodySecondBackgroundColor;
        }
    }

    var nextMinuteDate = new Date();
    nextMinuteDate.setMinutes(currentDate.getMinutes() + 1, 0, 0);
    var waitTime = nextMinuteDate.getTime() - currentDate.getTime();
    //Wait for the minute to finish and count down
    setTimeout(function () {
        countDown();
        //Count down every minute if entry has been expired, animate it out
        setInterval(countDown, 1000 * 60);
    }, waitTime);
});

function countDown() {
    var tableRows = document.getElementById("table-main").rows;
    var minuteIndex = 5;
    for (var i = 1; i < tableRows.length; i++) {
        if (parseInt(tableRows[i].cells[minuteIndex].innerHTML) === minuteLimit) {
            $("#table-main tr:eq(" + i + ")")
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

function getImageSrc(type) {
    var src;
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

function createMarquee(td, isEven) {
    if (isOverflown(td)) {
        var cssClass = "fade-out-odd";
        if (isEven) {
            cssClass = "fade-out-even";
        }
        td.innerHTML =
            "<div class='fade-out'>" +
            "<span class='marquee'>" + td.innerHTML + "</span>" +
            "<div class='" + cssClass + "'></div>" +
            "</div>";
    }
}

function isOverflown(element) {
    return element.scrollWidth > element.clientWidth;
}
