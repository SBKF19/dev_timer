document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    // Récupération des données depuis le HTML (dataset)
    const settings = calendarEl.dataset;
    const businessHours = JSON.parse(settings.businessHours);

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'fr',
        firstDay: 1,
        weekends: false,
        allDaySlot: false,
        height: 'auto',
        expandRows: false,
        slotMinTime: settings.minTime, 
        slotMaxTime: settings.maxTime,
        businessHours: businessHours, 
        selectConstraint: 'businessHours',
        selectable: true,
        events: settings.eventsUrl,

        selectOverlap: (event) => !event.id.startsWith('holiday_'),
        
        datesSet: (info) => updateStats(info.startStr, info.endStr, settings.statsUrl),

        select: function(info) {
            window.location.href = `${settings.addUrl}?start=${info.startStr}&end=${info.endStr}`;
        },

        eventClick: function(info) {
            if (info.event.id.startsWith('holiday_')) return;
            const numericId = info.event.id.replace('entry_', '');
            window.location.href = settings.editUrl.replace('PLACEHOLDER', numericId);
        }
    });

    calendar.render();
});

function updateStats(start, end, baseUrl) {
    fetch(`${baseUrl}?start=${start}&end=${end}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('stat-saisie').innerText = data.saisie;
            document.getElementById('stat-restant').innerText = data.restant;
        })
        .catch(err => console.error('Erreur stats:', err));
}