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

$site = load_site();
$heroImages = $site['hero_images'];
$services = $site['services'];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#152033">
  <?php
    $shareTitle = $site['share_title'];
    $shareText = $site['share_text'];
    $shareImage = site_url('assets/img/share.jpg');
  ?>
  <title><?= h($shareTitle) ?></title>
  <meta name="description" content="<?= h($shareText) ?>">
  <link rel="canonical" href="<?= h(site_url()) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= h($site['site_name']) ?>">
  <meta property="og:title" content="<?= h($shareTitle) ?>">
  <meta property="og:description" content="<?= h($shareText) ?>">
  <meta property="og:url" content="<?= h(site_url()) ?>">
  <meta property="og:image" content="<?= h($shareImage) ?>">
  <meta property="og:image:secure_url" content="<?= h($shareImage) ?>">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Anupriya Productions logo">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= h($shareTitle) ?>">
  <meta name="twitter:description" content="<?= h($shareText) ?>">
  <meta name="twitter:image" content="<?= h($shareImage) ?>">
  <link rel="icon" href="<?= h($site['logo_mark']['file']) ?>">
  <?php if ($heroImages): ?>
  <link rel="preload" as="image" href="<?= h($heroImages[0]['file']) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body id="top">
  <a class="skip" href="#main">Skip to content</a>
  <header class="site-header">
    <a class="brand" href="#top">
      <img src="<?= h($site['logo_mark']['file']) ?>" width="<?= (int)$site['logo_mark']['w'] ?>" height="<?= (int)$site['logo_mark']['h'] ?>" alt="">
      <span>
        <strong><?= h($site['site_name']) ?></strong>
        <small><?= h($site['brand_line']) ?></small>
      </span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav class="nav" id="site-nav">
      <a href="#about">About</a>
      <a href="#events">Events</a>
      <a href="#gallery">Gallery</a>
      <a href="#book">Book</a>
      <a href="<?= h(admin_public_path()) ?>">Login</a>
    </nav>
  </header>

  <main id="main">
    <section class="hero">
      <div class="hero-media">
        <div class="hero-photo">
          <?php foreach ($heroImages as $index => $photo): ?>
          <img<?= $index === 0 ? ' class="is-on"' : '' ?> src="<?= h($photo['file']) ?>" width="<?= (int)$photo['w'] ?>" height="<?= (int)$photo['h'] ?>" alt="<?= h($photo['alt']) ?>"<?= $index === 0 ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"' ?>>
          <?php endforeach; ?>
        </div>
        <?php if (count($heroImages) > 1): ?>
        <div class="hero-controls">
          <button class="hero-nav hero-prev" type="button" aria-label="Previous photo"></button>
          <div class="hero-dots" aria-label="Photos">
            <?php foreach ($heroImages as $index => $photo): ?>
            <button type="button"<?= $index === 0 ? ' class="is-on"' : '' ?> aria-label="Photo <?= $index + 1 ?>"></button>
            <?php endforeach; ?>
          </div>
          <button class="hero-nav hero-next" type="button" aria-label="Next photo"></button>
        </div>
        <?php endif; ?>
      </div>
      <div class="hero-copy">
        <p class="eyebrow"><?= h($site['hero_eyebrow']) ?></p>
        <h1><?= h($site['hero_title']) ?></h1>
        <p class="lede"><?= h($site['hero_lede']) ?></p>
        <div class="actions">
          <a class="btn btn-gold" href="#book"><?= h($site['hero_button']) ?></a>
          <?php if ($wa !== ''): ?>
          <a class="btn btn-line" href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section" id="about">
      <div class="wrap about-grid">
        <img src="<?= h($site['about_image']['file']) ?>" width="<?= (int)$site['about_image']['w'] ?>" height="<?= (int)$site['about_image']['h'] ?>" alt="<?= h($site['about_image']['alt']) ?>" loading="lazy" decoding="async">
        <div>
          <h2><?= h($site['about_heading']) ?></h2>
          <?php if ($site['about_1'] !== ''): ?><p><?= h($site['about_1']) ?></p><?php endif; ?>
          <?php if ($site['about_2'] !== ''): ?><p><?= h($site['about_2']) ?></p><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section" id="hosting">
      <div class="wrap">
        <h2><?= h($site['hosting_heading']) ?></h2>
        <div class="services">
          <?php foreach ($services as $index => $service): ?>
          <article class="service">
            <span><?= h(sprintf('%02d', $index + 1)) ?></span>
            <h3><?= h($service['title']) ?></h3>
            <p><?= h($service['text']) ?></p>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="events">
      <div class="wrap">
        <h2><?= h($site['events_heading']) ?></h2>
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
        <h2><?= h($site['gallery_heading']) ?></h2>
        <div class="gallery">
          <?php foreach ($site['gallery'] as $photo): ?>
          <figure class="shot<?= !empty($photo['wide']) ? ' shot-wide' : '' ?>">
            <img src="<?= h($photo['file']) ?>" width="<?= (int)$photo['w'] ?>" height="<?= (int)$photo['h'] ?>" alt="<?= h($photo['alt']) ?>" loading="lazy" decoding="async">
            <?php if ($photo['caption'] !== ''): ?><figcaption><?= h($photo['caption']) ?></figcaption><?php endif; ?>
          </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="book">
      <div class="wrap">
        <div class="book-panel">
          <h2><?= h($site['book_title']) ?></h2>
          <p class="book-lead"><?= h($site['book_lead']) ?></p>
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
      <img class="footer-logo" src="<?= h($site['logo']['file']) ?>" width="<?= (int)$site['logo']['w'] ?>" height="<?= (int)$site['logo']['h'] ?>" alt="<?= h($site['logo']['alt']) ?>">
      <p class="footer-name"><?= h($site['footer_name']) ?></p>
      <p><?= h($site['footer_line']) ?></p>
      <p><a class="footer-login" href="<?= h(admin_public_path()) ?>">Login</a></p>
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
