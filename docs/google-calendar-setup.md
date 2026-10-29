# Google Calendar connection

Manage Calendars supports two sources: existing iCal links and calendars selected from a connected Google account. The Google connection requests calendar-list and event read permissions separately from ordinary Google sign-in. Connecting a calendar never changes the signed-in Studio account.

## Deployment

1. Deploy the changed PHP files and `migrations/039_google_calendars.sql` together. The migration adds two tables without changing existing calendars. It can be applied normally before deployment; if the tables are absent, the Connect Google Calendar action attempts to create them using this migration. Hosts without schema-write permission must apply the migration separately.
2. In the Google Cloud project that owns the existing `GOOGLE_CLIENT_ID`, enable **Google Calendar API**.
3. Configure the OAuth consent screen for these scopes:
   - `https://www.googleapis.com/auth/calendar.calendarlist.readonly`
   - `https://www.googleapis.com/auth/calendar.events.readonly`
4. Continue using the existing Google callback URL. Production uses `https://readysetshows.com/studio/member/google_callback.php`; local development uses `GOOGLE_REDIRECT_URI`. Both must be registered for that OAuth client as appropriate.
5. While the Google application is in testing, add the intended Google accounts as test users. Follow Google's publishing/verification requirements before offering these permissions to all users. Google testing-mode refresh tokens can expire after seven days for these scopes, requiring reconnection.
6. Optional: configure a long, random `GOOGLE_CALENDAR_TOKEN_KEY` on the server before the first connection. Without it, the existing Google client secret is used to derive the encryption key. Changing whichever secret supplies the encryption key requires users to reconnect. Never expose this key to the browser or commit it to source control.

## User flow

1. Sign in to Studio, using Google sign-in or an existing Studio account.
2. Open Calendar → Calendars and choose **Connect Google Calendar**.
3. Select the Google account and allow read-only calendar-list and event access.
4. Choose a calendar from **Your Google calendars** and click **Add Google Calendar**. Repeat for additional calendars. Name, color, and timezone start with Google's values and can be edited afterward.
5. Select dates in Availability, Print, Export, or Finance. Google receives the requested start/end dates and expands recurring events only within that window. All response pages are read before results are reported.

One Google account can be connected per Studio account. Reconnecting a different Google account leaves existing saved calendars intact, but those calendars require their original account to be reconnected. Disconnect removes the app's stored Google credentials; it does not delete Google calendars or events. Google permissions may also be revoked in the user's Google account settings.

iCal links continue to work as before. A Google calendar saved through the picker uses a separate source record instead of an iCal URL. Deleting a saved calendar removes that source record through its foreign key.

## Validation

- `php studio/tests/google-calendar.test.php`
- `node studio/tests/calendar-export.test.cjs`
- `php studio/tests/calendar-picker-view.test.php`
- `php studio/tests/calendar-feed.test.php` (starts a temporary local HTTP fixture server)

Before release, complete one real Google consent cycle, check calendar listing, load a date range containing both recurring and all-day events, verify Print/Export/Finance, then disconnect and reconnect. Automated tests use fake credentials and mocked provider responses; they do not verify the Google Cloud project's API/consent configuration.

References: [Google OAuth web-server flow](https://developers.google.com/identity/protocols/oauth2/web-server), [Token expiration](https://developers.google.com/identity/protocols/oauth2/), [Calendar list](https://developers.google.com/workspace/calendar/api/v3/reference/calendarList/list), [Date-range event retrieval](https://developers.google.com/workspace/calendar/api/v3/reference/events/list).
