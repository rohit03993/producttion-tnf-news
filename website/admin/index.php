<?php

require dirname(__DIR__) . '/includes/bootstrap.php';

header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

$flash = '';
if (!empty($_SESSION['flash']) && is_string($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
$error = '';
$form = [
    'id' => '',
    'title' => '',
    'date' => '',
    'venue' => '',
    'line' => '',
    'type' => 'Wedding',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!csrf_ok()) {
        $error = 'The form expired. Try again.';
    } elseif ($action === 'login') {
        $password = (string)($_POST['password'] ?? '');
        $hash = site_config()['password_hash'];
        if ($hash === '') {
            $error = 'Login is off until a password hash is set in data/config.php.';
        } elseif ($hash[0] !== '$') {
            $error = 'data/config.php needs a password hash, not the plain password.';
        } elseif (login_blocked()) {
            $error = 'Too many tries. Wait ten minutes and try again.';
        } elseif (password_verify($password, $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = 1;
            login_note_success();
            header('Location: ' . admin_self());
            exit;
        } else {
            login_note_failure();
            $error = login_blocked()
                ? 'Too many tries. Wait ten minutes and try again.'
                : 'That password is not correct.';
        }
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . admin_self());
        exit;
    } elseif (!is_logged_in()) {
        $error = 'Please log in.';
    } elseif ($action === 'save') {
        $form['id'] = (string)($_POST['id'] ?? '');
        $form['title'] = clip((string)($_POST['title'] ?? ''), 140);
        $form['date'] = trim((string)($_POST['date'] ?? ''));
        $form['venue'] = clip((string)($_POST['venue'] ?? ''), 140);
        $form['line'] = clip((string)($_POST['line'] ?? ''), 280);
        $form['type'] = (string)($_POST['type'] ?? '');
        $id = $form['id'];
        if ($id !== '' && !preg_match('/^[a-f0-9]{16}$/', $id)) {
            $error = 'That event could not be found.';
        } elseif ($form['title'] === '') {
            $error = 'Add a title.';
        } elseif (!valid_date($form['date'])) {
            $error = 'Choose a valid date.';
        } elseif (!in_array($form['type'], event_types(), true)) {
            $error = 'Choose an event type.';
        } else {
            $events = read_json('events.json');
            $row = [
                'id' => $id !== '' ? $id : bin2hex(random_bytes(8)),
                'title' => $form['title'],
                'date' => $form['date'],
                'venue' => $form['venue'],
                'line' => $form['line'],
                'type' => $form['type'],
            ];
            $found = $id === '';
            if ($id !== '') {
                foreach ($events as $index => $event) {
                    if (is_array($event) && (string)($event['id'] ?? '') === $id) {
                        $events[$index] = $row;
                        $found = true;
                        break;
                    }
                }
            } else {
                $events[] = $row;
            }
            if (!$found) {
                $error = 'That event could not be found.';
            } elseif (!write_json('events.json', array_values($events))) {
                $error = 'Could not save. Make sure the data folder is writable.';
            } else {
                $_SESSION['flash'] = $id !== '' ? 'Event updated.' : 'Event added.';
                header('Location: ' . admin_self());
                exit;
            }
        }
    } elseif ($action === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            $error = 'That event could not be found.';
        } else {
            $events = array_values(array_filter(read_json('events.json'), function ($event) use ($id) {
                return !is_array($event) || (string)($event['id'] ?? '') !== $id;
            }));
            if (!write_json('events.json', $events)) {
                $error = 'Could not remove that event. Make sure the data folder is writable.';
            } else {
                $_SESSION['flash'] = 'Event removed.';
                header('Location: ' . admin_self());
                exit;
            }
        }
    }
}

$editing = null;
if ($error === '' && isset($_GET['edit']) && preg_match('/^[a-f0-9]{16}$/', (string)$_GET['edit'])) {
    foreach (read_json('events.json') as $event) {
        if (is_array($event) && (string)($event['id'] ?? '') === (string)$_GET['edit']) {
            $editing = $event;
            $form = [
                'id' => (string)($event['id'] ?? ''),
                'title' => (string)($event['title'] ?? ''),
                'date' => (string)($event['date'] ?? ''),
                'venue' => (string)($event['venue'] ?? ''),
                'line' => (string)($event['line'] ?? ''),
                'type' => (string)($event['type'] ?? 'Wedding'),
            ];
            break;
        }
    }
}

$view = (string)($_GET['view'] ?? 'events');
if ($view !== 'enquiries') {
    $view = 'events';
}

$events = read_json('events.json');
usort($events, function ($a, $b) {
    return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
});
$enquiries = array_reverse(read_json('enquiries.json'));
$today = today_string();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Admin — Anupriya Productions</title>
  <link rel="icon" href="../assets/img/logo-mark.webp" type="image/webp">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
  <div class="admin-wrap">
    <?php if (!is_logged_in()): ?>
    <form class="admin-card login-card" method="post" action="<?= h(admin_self()) ?>">
      <img class="login-mark" src="../assets/img/logo-mark.webp" width="168" height="167" alt="">
      <p class="eyebrow">Anupriya Productions</p>
      <h1>Sign in</h1>
      <?php if ($error !== ''): ?><p class="alert" role="alert"><?= h($error) ?></p><?php endif; ?>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <label for="password">Password
        <input id="password" name="password" type="password" required autocomplete="current-password">
      </label>
      <button class="btn btn-gold" type="submit">Sign in</button>
      <p class="quiet">After signing in, add an event. Today or a later date is upcoming. An earlier date is past.</p>
    </form>
    <?php else: ?>
    <header class="admin-top">
      <a class="brand" href="../index.php">
        <img src="../assets/img/logo-mark.webp" width="168" height="167" alt="">
        <span>
          <strong>Anupriya Productions</strong>
          <small>Admin</small>
        </span>
      </a>
      <form method="post" action="<?= h(admin_self()) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button class="btn btn-line" type="submit">Log out</button>
      </form>
    </header>
    <nav class="admin-nav">
      <a href="<?= h(admin_self()) ?>"<?= $view === 'events' ? ' aria-current="page"' : '' ?>>Events</a>
      <a href="<?= h(admin_self('view=enquiries')) ?>"<?= $view === 'enquiries' ? ' aria-current="page"' : '' ?>>Enquiries</a>
      <a href="../index.php">View site</a>
    </nav>
    <h1><?= $view === 'enquiries' ? 'Enquiries' : 'Events' ?></h1>
    <?php if ($flash !== ''): ?><p class="notice" role="status"><?= h($flash) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="alert" role="alert"><?= h($error) ?></p><?php endif; ?>

    <?php if ($view === 'enquiries'): ?>
      <section class="admin-list" aria-label="Enquiries">
      <?php if (!$enquiries): ?>
      <p class="admin-empty">No enquiries yet. Notes from the public booking form will appear here.</p>
      <?php endif; ?>
      <?php foreach ($enquiries as $enquiry): ?>
        <?php if (!is_array($enquiry)) { continue; } ?>
        <?php
          $phone = (string)($enquiry['phone'] ?? '');
          $digits = preg_replace('/\D+/', '', $phone);
          $when = (string)($enquiry['date'] ?? '');
        ?>
        <article class="admin-card">
          <?php
            $at = (string)($enquiry['at'] ?? '');
            $received = $at;
            if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', $at, $match)) {
                $received = format_event_date($match[1]) . ', ' . $match[2];
            }
          ?>
          <p class="event-date">Received <?= h($received) ?></p>
          <h2><?= h((string)($enquiry['name'] ?? '')) ?></h2>
          <dl class="details">
            <div>
              <dt>Phone</dt>
              <dd>
                <?php if ($digits !== ''): ?>
                <a href="tel:<?= h($digits) ?>"><?= h($phone) ?></a>
                <?php else: ?>
                <?= h($phone !== '' ? $phone : '—') ?>
                <?php endif; ?>
              </dd>
            </div>
            <div>
              <dt>Event type</dt>
              <dd><?= h((string)($enquiry['type'] ?? '') !== '' ? (string)$enquiry['type'] : '—') ?></dd>
            </div>
            <div>
              <dt>Date</dt>
              <dd><?= h($when !== '' ? format_event_date($when) : '—') ?></dd>
            </div>
            <div>
              <dt>Message</dt>
              <dd class="message"><?= (string)($enquiry['message'] ?? '') !== '' ? nl2br(h((string)$enquiry['message'])) : '—' ?></dd>
            </div>
          </dl>
        </article>
      <?php endforeach; ?>
      </section>
    <?php else: ?>
      <div class="admin-desk">
      <form class="admin-card" method="post" action="<?= h(admin_self()) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h($form['id']) ?>">
        <h2><?= $form['id'] !== '' ? 'Edit event' : 'Add event' ?></h2>
        <label for="title">Title
          <input id="title" name="title" type="text" required maxlength="140" value="<?= h($form['title']) ?>">
        </label>
        <div class="form-row">
          <label for="event-date">Date
            <input id="event-date" name="date" type="date" required value="<?= h($form['date']) ?>">
          </label>
          <label for="event-type">Type
            <select id="event-type" name="type" required>
              <?php foreach (event_types() as $type): ?>
              <option value="<?= h($type) ?>"<?= $form['type'] === $type ? ' selected' : '' ?>><?= h($type) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <label for="venue">Venue
          <input id="venue" name="venue" type="text" maxlength="140" value="<?= h($form['venue']) ?>">
        </label>
        <label for="line">Short line
          <input id="line" name="line" type="text" maxlength="280" value="<?= h($form['line']) ?>">
        </label>
        <div class="admin-actions">
          <button class="btn btn-gold" type="submit"><?= $form['id'] !== '' ? 'Update' : 'Add event' ?></button>
          <?php if ($form['id'] !== ''): ?><a class="btn btn-line" href="<?= h(admin_self()) ?>">Cancel</a><?php endif; ?>
        </div>
        <p class="quiet">A date of today or later is upcoming. An earlier date is past. Today is <?= h(format_event_date($today)) ?>.</p>
      </form>

      <section class="admin-list" aria-label="Saved events">
      <h2>On the site</h2>
      <?php if (!$events): ?>
      <p class="admin-empty">No events yet. Add one and it will show on the public page.</p>
      <?php endif; ?>
      <?php foreach ($events as $event): ?>
        <?php if (!is_array($event) || empty($event['id'])) { continue; } ?>
        <?php $when = (string)($event['date'] ?? ''); ?>
        <article class="admin-card">
          <p class="event-date"><?= h(format_event_date($when)) ?> · <span class="status<?= $when >= $today ? ' is-upcoming' : ' is-past' ?>"><?= $when >= $today ? 'Upcoming' : 'Past' ?></span></p>
          <h2><?= h((string)($event['title'] ?? '')) ?></h2>
          <p><?= h((string)($event['type'] ?? '')) ?><?php if ((string)($event['venue'] ?? '') !== ''): ?> · <?= h((string)$event['venue']) ?><?php endif; ?></p>
          <?php if ((string)($event['line'] ?? '') !== ''): ?><p><?= h((string)$event['line']) ?></p><?php endif; ?>
          <div class="admin-actions">
            <a class="btn btn-line" href="<?= h(admin_self('edit=' . (string)$event['id'])) ?>">Edit</a>
            <form method="post" action="<?= h(admin_self()) ?>" data-confirm="Remove this event?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= h((string)$event['id']) ?>">
              <button class="btn btn-danger" type="submit">Delete</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
      </section>
      </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
  <script src="../assets/js/site.js"></script>
</body>
</html>
