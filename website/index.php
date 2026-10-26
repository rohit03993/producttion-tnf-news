<?php

require __DIR__ . '/includes/bootstrap.php';

header('Cache-Control: private, no-cache');

$errors = [];
$old = [
    'name' => '',
    'phone' => '',
    'type' => '',
    'date' => '',
    'message' => '',
];
$sent = isset($_GET['sent']) && $_GET['sent'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'enquire') {
    $old['name'] = clip((string)($_POST['name'] ?? ''), 120);
    $old['phone'] = clip((string)($_POST['phone'] ?? ''), 20);
    $old['type'] = (string)($_POST['type'] ?? '');
    $old['date'] = trim((string)($_POST['date'] ?? ''));
    $old['message'] = clip((string)($_POST['message'] ?? ''), 2000);
    $last = (int)($_SESSION['enquiry_at'] ?? 0);

    if (!csrf_ok()) {
        $errors[] = 'The form expired. Please send it again.';
    } elseif (trim((string)($_POST['leave_blank'] ?? '')) !== '') {
        header('Location: ' . public_self('sent=1') . '#book');
        exit;
    } elseif ($last > 0 && (time() - $last) < 15) {
        $errors[] = 'Please wait a moment, then send it again.';
    } else {
        $digits = preg_replace('/\D+/', '', $old['phone']);
        if ($old['name'] === '') {
            $errors[] = 'Add your name.';
        }
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            $errors[] = 'Enter a phone number we can call.';
        }
        if (!in_array($old['type'], event_types(), true)) {
            $errors[] = 'Choose the kind of event.';
        }
        if ($old['date'] !== '' && !valid_date($old['date'])) {
            $errors[] = 'Check the preferred date.';
        }
        if (!$errors) {
            $enquiries = read_json('enquiries.json');
            $enquiries[] = [
                'id' => bin2hex(random_bytes(8)),
                'name' => $old['name'],
                'phone' => $old['phone'],
                'type' => $old['type'],
                'date' => $old['date'],
                'message' => $old['message'],
                'at' => now_stamp(),
            ];
            if (!write_json('enquiries.json', $enquiries)) {
                $errors[] = 'Could not save that note. Please try again.';
            } else {
                $_SESSION['enquiry_at'] = time();
                header('Location: ' . public_self('sent=1') . '#book');
                exit;
            }
        }
    }
}

$wa = whatsapp_link();
[$upcoming, $past] = split_events(read_json('events.json'), today_string());

$gallery = [
    ['file' => 'g-001.webp', 'w' => 798, 'h' => 1000, 'alt' => 'Anupriya Tripathi hosting a wedding, microphone in hand, on a floral stage', 'caption' => 'Wedding', 'wide' => false],
    ['file' => 'g-016.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi speaking into a microphone in a red gown during a wedding', 'caption' => 'Live hosting', 'wide' => false],
    ['file' => 'g-007.webp', 'w' => 686, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a black gown in front of a lit stage screen', 'caption' => 'On stage', 'wide' => false],
    ['file' => 'g-008.webp', 'w' => 679, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a navy gown against a flower wall', 'caption' => 'Celebration', 'wide' => false],
    ['file' => 'g-011.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a red gown on a decorated wedding stage', 'caption' => 'Wedding stage', 'wide' => false],
    ['file' => 'g-015.webp', 'w' => 750, 'h' => 1000, 'alt' => 'Anupriya Tripathi in a silver lehenga at a reception', 'caption' => 'Reception', 'wide' => false],
    ['file' => 'g-006.webp', 'w' => 562, 'h' => 1000, 'alt' => 'Anupriya Tripathi at an outdoor haldi celebration', 'caption' => 'Haldi', 'wide' => false],
    ['file' => 'g-021.webp', 'w' => 450, 'h' => 1000, 'alt' => 'Anupriya Tripathi hosting a school function with a microphone', 'caption' => 'School function', 'wide' => false],
    ['file' => 'g-022.webp', 'w' => 563, 'h' => 1000, 'alt' => 'Anupriya Tripathi with a media pass at the UP International Trade Show', 'caption' => 'Trade show', 'wide' => false],
    ['file' => 'g-023.webp', 'w' => 1000, 'h' => 667, 'alt' => 'Anupriya Tripathi speaking at a podium', 'caption' => 'At the podium', 'wide' => true],
    ['file' => 'g-026.webp', 'w' => 750, 'h' => 1000, 'alt' => 'Anupriya Tripathi at a brand launch', 'caption' => 'Brand launch', 'wide' => false],
];

function event_cards(array $events, bool $join = false): void
{
    echo '<div class="event-list">';
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $title = (string)($event['title'] ?? '');
        $date = (string)($event['date'] ?? '');
        $type = (string)($event['type'] ?? '');
        $venue = (string)($event['venue'] ?? '');
        $line = (string)($event['line'] ?? '');
        $meta = array_filter([$type, $venue], function ($part) {
            return $part !== '';
        });
        echo '<article class="event">';
        echo '<div class="event-body">';
        echo '<p class="event-date">' . h(format_event_date($date)) . '</p>';
        echo '<h4>' . h($title) . '</h4>';
        if ($meta) {
            echo '<p class="meta">' . h(implode(' · ', $meta)) . '</p>';
        }
        if ($line !== '') {
            echo '<p>' . h($line) . '</p>';
        }
        echo '</div>';
        if ($join && $title !== '') {
            echo '<a class="btn btn-gold" href="#book" data-join-type="' . h($type) . '" data-join-date="' . h($date) . '" data-join-message="' . h('I would like to join ' . $title . '.') . '">Join the event</a>';
        }
        echo '</article>';
    }
    echo '</div>';
}

$services = [
    ['01', 'Weddings', 'Hosting for wedding celebrations, from the haldi to the reception.'],
    ['02', 'Corporate and trade', 'Conferences, trade shows, and formal programmes.'],
    ['03', 'School functions', 'Assemblies, annual days, and other school events.'],
    ['04', 'Brand launches', 'Product launches and in-store activations.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#152033">
  <title>Anupriya Productions — Event Management &amp; Media Services</title>
  <meta name="description" content="Anupriya Productions, led by Anupriya Tripathi, hosts weddings, school functions, corporate events, and brand launches.">
  <link rel="icon" href="assets/img/logo-mark.webp" type="image/webp">
  <link rel="preload" as="image" href="assets/img/hero.webp" type="image/webp">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body id="top">
  <a class="skip" href="#main">Skip to content</a>
  <header class="site-header">
    <a class="brand" href="#top">
      <img src="assets/img/logo-mark.webp" width="168" height="167" alt="">
      <span>
        <strong>Anupriya Productions</strong>
        <small>Event Management &amp; Media Services</small>
      </span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav class="nav" id="site-nav">
      <a href="#about">About</a>
      <a href="#events">Events</a>
      <a href="#gallery">Gallery</a>
      <a href="#book">Book</a>
    </nav>
  </header>

  <main id="main">
    <section class="hero">
      <div class="hero-media">
        <div class="hero-photo">
          <img class="is-on" src="assets/img/hero.webp" width="1021" height="1280" alt="Anupriya Tripathi hosting a wedding on a floral stage" fetchpriority="high">
          <img src="assets/img/g-016.webp" width="562" height="1000" alt="Anupriya Tripathi speaking into a microphone in a red gown" loading="lazy" decoding="async">
          <img src="assets/img/g-008.webp" width="679" height="1000" alt="Anupriya Tripathi in a navy gown against a flower wall" loading="lazy" decoding="async">
          <img src="assets/img/g-007.webp" width="686" height="1000" alt="Anupriya Tripathi in a black gown on stage" loading="lazy" decoding="async">
          <img src="assets/img/g-015.webp" width="750" height="1000" alt="Anupriya Tripathi at a reception" loading="lazy" decoding="async">
          <img src="assets/img/g-022.webp" width="563" height="1000" alt="Anupriya Tripathi at a trade show" loading="lazy" decoding="async">
        </div>
        <div class="hero-controls">
          <button class="hero-nav hero-prev" type="button" aria-label="Previous photo"></button>
          <div class="hero-dots" aria-label="Photos">
            <button type="button" class="is-on" aria-label="Photo 1"></button>
            <button type="button" aria-label="Photo 2"></button>
            <button type="button" aria-label="Photo 3"></button>
            <button type="button" aria-label="Photo 4"></button>
            <button type="button" aria-label="Photo 5"></button>
            <button type="button" aria-label="Photo 6"></button>
          </div>
          <button class="hero-nav hero-next" type="button" aria-label="Next photo"></button>
        </div>
      </div>
      <div class="hero-copy">
        <p class="eyebrow">Event Management &amp; Media Services</p>
        <h1>Anupriya Tripathi</h1>
        <p class="lede">On the microphone for weddings, school functions, corporate events, and brand launches.</p>
        <div class="actions">
          <a class="btn btn-gold" href="#book">Book an event</a>
          <?php if ($wa !== ''): ?>
          <a class="btn btn-line" href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section" id="about">
      <div class="wrap about-grid">
        <img src="assets/img/about.webp" width="781" height="1000" alt="Anupriya Tripathi in a black suit" loading="lazy" decoding="async">
        <div>
          <h2>About</h2>
          <p>Anupriya Productions is the event and media work of Anupriya Tripathi. These photographs are from wedding stages, a school function, a trade show where she carried a media pass, and a brand launch.</p>
          <p>She anchors from the stage and the podium, and she stays with the audience.</p>
        </div>
      </div>
    </section>

    <section class="section" id="hosting">
      <div class="wrap">
        <h2>Hosting</h2>
        <div class="services">
          <?php foreach ($services as $service): ?>
          <article class="service">
            <span><?= h($service[0]) ?></span>
            <h3><?= h($service[1]) ?></h3>
            <p><?= h($service[2]) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="events">
      <div class="wrap">
        <h2>Events</h2>
        <div class="event-cols">
          <div>
            <h3>Upcoming</h3>
            <?php if (!$upcoming): ?>
            <p class="quiet">New dates will be announced here.</p>
            <?php else: ?>
            <?php event_cards($upcoming, true); ?>
            <?php endif; ?>
          </div>
          <?php if ($past): ?>
          <div>
            <h3>Past</h3>
            <?php event_cards($past); ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section" id="gallery">
      <div class="wrap">
        <h2>Gallery</h2>
        <div class="gallery">
          <?php foreach ($gallery as $photo): ?>
          <figure class="shot<?= $photo['wide'] ? ' shot-wide' : '' ?>">
            <img src="assets/img/<?= h($photo['file']) ?>" width="<?= (int)$photo['w'] ?>" height="<?= (int)$photo['h'] ?>" alt="<?= h($photo['alt']) ?>" loading="lazy" decoding="async">
            <figcaption><?= h($photo['caption']) ?></figcaption>
          </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="book">
      <div class="wrap">
        <div class="book-panel">
          <h2>Book an event</h2>
          <p class="book-lead">Send the date and the kind of programme. The note is saved for Anupriya.</p>
          <?php if ($sent): ?>
          <p class="notice" role="status">Your note is saved.<?php if ($wa !== ''): ?> You can also message on WhatsApp.<?php endif; ?></p>
          <?php endif; ?>
          <?php foreach ($errors as $error): ?>
          <p class="alert" role="alert"><?= h($error) ?></p>
          <?php endforeach; ?>
          <form method="post" action="<?= h(public_self()) ?>#book">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="enquire">
            <div class="hp" aria-hidden="true">
              <label for="leave_blank">Leave blank</label>
              <input type="text" name="leave_blank" id="leave_blank" tabindex="-1" autocomplete="off">
            </div>
            <div class="form-row">
              <label for="name">Name
                <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="<?= h($old['name']) ?>">
              </label>
              <label for="phone">Phone
                <input id="phone" name="phone" type="tel" required maxlength="20" autocomplete="tel" inputmode="tel" value="<?= h($old['phone']) ?>">
              </label>
            </div>
            <div class="form-row">
              <label for="type">Event type
                <select id="type" name="type" required>
                  <option value=""<?= $old['type'] === '' ? ' selected' : '' ?> disabled>Choose</option>
                  <?php foreach (event_types() as $type): ?>
                  <option value="<?= h($type) ?>"<?= $old['type'] === $type ? ' selected' : '' ?>><?= h($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label for="date">Preferred date
                <input id="date" name="date" type="date" value="<?= h($old['date']) ?>">
              </label>
            </div>
            <label for="message">Message
              <textarea id="message" name="message" maxlength="2000" rows="4"><?= h($old['message']) ?></textarea>
            </label>
            <button class="btn btn-gold" type="submit">Send</button>
          </form>
          <?php if ($wa !== ''): ?>
          <p class="book-alt"><a class="btn btn-line btn-line-light" href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a></p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="wrap">
      <img class="footer-logo" src="assets/img/logo.webp" width="377" height="445" alt="Anupriya Productions, Event Management and Media Services">
      <p class="footer-name">Anupriya Tripathi</p>
      <p>Anchor &amp; Event Host</p>
    </div>
  </footer>

  <div class="mobile-bar<?= $wa === '' ? ' is-single' : '' ?>">
    <a class="btn btn-gold" href="#book">Book</a>
    <?php if ($wa !== ''): ?>
    <a class="btn btn-line btn-line-light" href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
    <?php endif; ?>
  </div>
  <script src="assets/js/site.js"></script>
</body>
</html>
