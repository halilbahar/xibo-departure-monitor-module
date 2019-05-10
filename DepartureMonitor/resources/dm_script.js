$(function () {

    //Look for expired entries, if you find one delete it
    let currentDate = new Date();
    let currentMinutes = currentDate.getHours() * 60 + currentDate.getMinutes();
    let index = 0;
    while (index < data.length) {
        if (data[index].arrivalTime.hour * 60 + data[index].arrivalTime.minute - currentMinutes < 0) {
            data.splice(index, 1);
        } else {
            index++;
        }
    }

    //Generate rows for every entry
    let table = document.getElementById("traffic-schedule");
    for (let i = 0; i < data.length; i++) {
        let tr = table.getElementsByTagName("tbody")[0].insertRow(-1);
        let td = [];
        for (let j = 0; j < 6; j++) {
            td[j] = tr.insertCell(j);
            td[j].classList.add("column" + (j + 1));
        }
        let hour = data[i].arrivalTime.hour;
        let minute = data[i].arrivalTime.minute;
        td[0].innerHTML = `<img src='${getImageSrc(data[i].type)}'>`;
        td[1].innerHTML = data[i].number;
        td[2].innerHTML = data[i].from;
        td[3].innerHTML = data[i].to;
        td[4].innerHTML = (hour < 10 ? "0" + hour : hour) + ":" + (minute < 10 ? "0" + minute : minute);
        let entryTime = hour * 60 + minute;
        td[5].innerHTML = entryTime - currentMinutes;
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
    let tableRows = document.getElementById("traffic-schedule").rows;
    let minuteIndex = 5;
    for (let i = 1; i < tableRows.length; i++) {
        if (parseInt(tableRows[i].cells[minuteIndex].innerHTML) === 0) {
            $(`#traffic-schedule tr:eq(${i})`)
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
    for (let i = 0; i < rows.length; i++) {
        if (i % 2 === 0) {
            rows[i].style.backgroundColor = "#f5f5f5";
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