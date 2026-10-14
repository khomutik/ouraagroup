(() => {
  const panel = document.querySelector('[data-meeting-countdown]');
  if (!panel) return;

  const weekdays = new Set(JSON.parse(panel.dataset.weekdays || '[]').map(Number));
  const hour = Number(panel.dataset.hour || 21);
  const minute = Number(panel.dataset.minute || 30);
  const durationMinutes = Number(panel.dataset.durationMinutes || 85);
  const label = panel.querySelector('.meeting-countdown__label');
  const value = panel.querySelector('.meeting-countdown__value');
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Europe/Moscow', year: 'numeric', month: '2-digit', day: '2-digit',
    weekday: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
  });

  function moscowParts(date) {
    return Object.fromEntries(formatter.formatToParts(date).map(({type, value}) => [type, value]));
  }

  function moscowWallTimeToTimestamp(wallTime) {
    let timestamp = wallTime;
    for (let attempt = 0; attempt < 3; attempt++) {
      const p = moscowParts(new Date(timestamp));
      const actualWallTime = Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day), Number(p.hour), Number(p.minute), Number(p.second));
      timestamp += wallTime - actualWallTime;
    }
    return timestamp;
  }

  function plural(number, one, few, many) {
    const lastTwo = number % 100;
    if (lastTwo >= 11 && lastTwo <= 14) return many;
    const last = number % 10;
    if (last === 1) return one;
    if (last >= 2 && last <= 4) return few;
    return many;
  }

  function nextMeeting(now) {
    const p = moscowParts(now);
    const year = Number(p.year), month = Number(p.month), day = Number(p.day);
    const wallNow = Date.UTC(year, month - 1, day, Number(p.hour), Number(p.minute), Number(p.second));
    const firstDay = Date.UTC(year, month - 1, day);

    for (let offset = 0; offset <= 7; offset++) {
      const candidateDay = new Date(firstDay + offset * 86400000);
      const isoWeekday = candidateDay.getUTCDay() || 7;
      if (!weekdays.has(isoWeekday)) continue;
      const wallTime = Date.UTC(candidateDay.getUTCFullYear(), candidateDay.getUTCMonth(), candidateDay.getUTCDate(), hour, minute);
      const timestamp = moscowWallTimeToTimestamp(wallTime);
      if (timestamp > now.getTime()) return timestamp;
      if (wallTime > wallNow) return timestamp;
    }
    return null;
  }

  function update() {
    if (!value) return;
    const now = new Date();
    const p = moscowParts(now);
    const today = Date.UTC(Number(p.year), Number(p.month) - 1, Number(p.day));
    const weekday = new Date(today).getUTCDay() || 7;
    const minuteOfDay = Number(p.hour) * 60 + Number(p.minute);
    const meetingStart = hour * 60 + minute;
    const isLive = weekdays.has(weekday)
      && minuteOfDay >= meetingStart
      && minuteOfDay < meetingStart + durationMinutes;

    panel.classList.toggle('is-live', isLive);
    if (isLive) {
      if (label) label.textContent = 'Сейчас идет собрание';
      value.textContent = '';
      return;
    }
    if (label) label.textContent = 'До собрания';
    const target = nextMeeting(now);
    if (!target) { value.textContent = 'Скоро встретимся'; return; }
    const totalMinutes = Math.max(0, Math.ceil((target - Date.now()) / 60000));
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    value.textContent = `${hours} ${plural(hours, 'час', 'часа', 'часов')} ${minutes} ${plural(minutes, 'минута', 'минуты', 'минут')}`;
  }

  update();
  window.setInterval(update, 15000);
})();
