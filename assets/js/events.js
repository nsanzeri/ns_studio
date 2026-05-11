// Uses Google Calendar to pull upcoming events and display them
// Replace with your own Calendar ID/API key if needed.
const EVENTS_API_KEY = 'AIzaSyAAn7Lr5DEU6hd53JGEfEi9cQ2XQ_peAFY';
const CALENDAR_ID = 'pjjfdgelvdjtuvrr89tun3nu7k@group.calendar.google.com';
const MAX_RESULTS = 40;

function formatEventDate(dateObj) {
    const weekday = dateObj.toLocaleDateString(undefined, { weekday: 'short' });
    const month = dateObj.toLocaleDateString(undefined, { month: 'short' });
    const day = dateObj.toLocaleDateString(undefined, { day: 'numeric' });
    const time = dateObj.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true });
    return { weekday, month, day, time };
}

function buildEvents() {
    const timeMin = new Date().toISOString();
    const url = `https://www.googleapis.com/calendar/v3/calendars/${encodeURIComponent(CALENDAR_ID)}/events?key=${EVENTS_API_KEY}&maxResults=${MAX_RESULTS}&orderBy=startTime&singleEvents=true&timeMin=${timeMin}`;

    $.getJSON(url, function (data) {
        if (!data.items) return;

        const eventsListEl = document.getElementById('eventsList');
        const nextShowsEl = document.getElementById('nextShows');
        let nextShowsHtml = '';
        let nextCount = 0;

        const eventsHtml = data.items.map(event => {
            const raw = event.start.dateTime || event.start.date;
            const dateObj = new Date(raw);
            if (isNaN(dateObj.getTime())) return '';

            const { weekday, month, day, time } = formatEventDate(dateObj);
            const now = new Date();

            let title = (event.summary || '').replace(/Nick @/i, '').trim();
            const location = event.location || '';
            const mapLink = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location)}`;

            const locationHtml = location
                ? `<a href="${mapLink}" target="_blank" rel="noopener noreferrer" class="event-location-link">${location}</a>`
                : '';

            // For "Up Next" card, grab first 3 upcoming
            if (nextShowsEl && dateObj >= now && nextCount < 3) {
                nextShowsHtml += `
                    <div class="next-show">
                        <div class="next-show-date">
                            <span class="weekday">${weekday}</span>
                            <span class="month-day">${month} ${day}</span>
                            <span class="time">${time}</span>
                        </div>
                        <div class="next-show-info">
                            <p class="next-show-title">${title}</p>
                            <p class="next-show-location">${locationHtml}</p>
                        </div>
                    </div>
                `;
                nextCount++;
            }

            return `
                <div class="next-show">
                    <div class="next-show-date">
                        <span class="weekday">${weekday}</span>
                        <span class="month-day">${month} ${day}</span>
                        <span class="time">${time}</span>
                    </div>
                    <div class="next-show-info">
                        <p class="next-show-title">${title}</p>
                        <p class="next-show-location">${locationHtml}</p>
                    </div>
                </div>
           `;

        });

        if (eventsListEl) {
            eventsListEl.innerHTML = eventsHtml.join('') || '<p>No upcoming events found.</p>';
        }

        if (nextShowsEl) {
            nextShowsEl.innerHTML = nextShowsHtml || '<p>No upcoming shows this week.</p>';
        }
    });
}

document.addEventListener('DOMContentLoaded', buildEvents);