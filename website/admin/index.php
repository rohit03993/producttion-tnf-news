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
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $next = (string)($_POST['new_password'] ?? '');
        $again = (string)($_POST['confirm_password'] ?? '');
        $hash = site_config()['password_hash'];
        if (login_blocked()) {
            $error = 'Too many tries. Wait ten minutes and try again.';
        } elseif ($hash === '' || $hash[0] !== '$' || !password_verify($current, $hash)) {
            login_note_failure();
            $error = login_blocked()
                ? 'Too many tries. Wait ten minutes and try again.'
                : 'The current password is not correct.';
        } elseif (strlen($next) < 8) {
            $error = 'Use at least 8 characters for the new password.';
        } elseif ($next !== $again) {
            $error = 'The new passwords do not match.';
        } elseif ($next === $current) {
            $error = 'Choose a password that is different from the current one.';
        } else {
            $config = site_config();
            $config['password_hash'] = password_hash($next, PASSWORD_DEFAULT);
            if (!save_site_config($config)) {
                $error = 'Could not save the password. Make sure data/config.php is writable.';
            } else {
                session_regenerate_id(true);
                login_note_success();
                $_SESSION['flash'] = 'Password updated. Use it the next time you sign in.';
                header('Location: ' . admin_self('view=password'));
                exit;
            }
        }
    } elseif ($action === 'save-text') {
        $site = load_site();
        $limits = [
            'site_name' => 80,
            'brand_line' => 80,
            'hero_eyebrow' => 80,
            'hero_title' => 80,
            'hero_lede' => 280,
            'hero_button' => 40,
            'about_heading' => 40,
            'about_1' => 800,
            'about_2' => 800,
            'hosting_heading' => 40,
            'events_heading' => 40,
            'gallery_heading' => 40,
            'book_title' => 80,
            'book_lead' => 280,
            'footer_name' => 80,
            'footer_line' => 80,
            'share_title' => 140,
            'share_text' => 300,
        ];
        foreach ($limits as $key => $max) {
            $site[$key] = clip((string)($_POST[$key] ?? ''), $max);
        }
        $services = [];
        $titles = $_POST['service_title'] ?? [];
        $texts = $_POST['service_text'] ?? [];
        if (is_array($titles) && is_array($texts)) {
            for ($i = 0; $i < 4; $i++) {
                $title = clip((string)($titles[$i] ?? ''), 80);
                $text = clip((string)($texts[$i] ?? ''), 240);
                if ($title === '' && $text === '') {
                    continue;
                }
                $services[] = ['title' => $title, 'text' => $text];
            }
        }
        if ($site['site_name'] === '' || $site['hero_title'] === '') {
            $error = 'The site name and the main heading are required.';
        } elseif (!$services) {
            $error = 'Keep at least one hosting item.';
        } elseif (!save_site($site)) {
            $error = 'Could not save the words. Make sure the data folder is writable.';
        } else {
            $_SESSION['flash'] = 'The words on the site are updated.';
            header('Location: ' . admin_self('view=text'));
            exit;
        }
    } elseif ($action === 'replace-image' || $action === 'image-label') {
        $slot = (string)($_POST['slot'] ?? '');
        $site = load_site();
        if (!isset($site[$slot]['file'])) {
            $error = 'That picture could not be found.';
        } elseif ($action === 'image-label') {
            $site[$slot]['alt'] = clip((string)($_POST['alt'] ?? ''), 180);
            if (!save_site($site)) {
                $error = 'Could not save that description.';
            } else {
                $_SESSION['flash'] = 'Picture description saved.';
                header('Location: ' . admin_self('view=photos'));
                exit;
            }
        } else {
            $stored = store_upload($_FILES['image'] ?? []);
            if (isset($stored['error'])) {
                $error = $stored['error'];
            } else {
                $old = (string)$site[$slot]['file'];
                $site[$slot] = [
                    'file' => $stored['file'],
                    'w' => $stored['w'],
                    'h' => $stored['h'],
                    'alt' => clip((string)($_POST['alt'] ?? $site[$slot]['alt'] ?? ''), 180),
                    'caption' => '',
                    'wide' => false,
                ];
                if (!save_site($site)) {
                    forget_upload($stored['file']);
                    $error = 'Could not save that picture.';
                } else {
                    if ($old !== $stored['file']) {
                        forget_upload($old);
                    }
                    $_SESSION['flash'] = 'Picture updated.';
                    header('Location: ' . admin_self('view=photos'));
                    exit;
                }
            }
        }
    } elseif ($action === 'add-image') {
        $group = (string)($_POST['group'] ?? '');
        $max = $group === 'hero_images' ? 8 : ($group === 'gallery' ? 24 : 0);
        $site = load_site();
        if ($max === 0) {
            $error = 'That picture list could not be found.';
        } elseif (count($site[$group]) >= $max) {
            $error = 'Remove a picture before adding another.';
        } else {
            $stored = store_upload($_FILES['image'] ?? []);
            if (isset($stored['error'])) {
                $error = $stored['error'];
            } else {
                $caption = clip((string)($_POST['caption'] ?? ''), 80);
                $alt = clip((string)($_POST['alt'] ?? ''), 180);
                if ($group === 'gallery' && $alt === '') {
                    $alt = $caption;
                }
                $site[$group][] = [
                    'file' => $stored['file'],
                    'w' => $stored['w'],
                    'h' => $stored['h'],
                    'alt' => $alt,
                    'caption' => $caption,
                    'wide' => $group === 'gallery' && !empty($_POST['wide']),
                ];
                if (!save_site($site)) {
                    forget_upload($stored['file']);
                    $error = 'Could not save that picture.';
                } else {
                    $_SESSION['flash'] = 'Picture added.';
                    header('Location: ' . admin_self('view=photos'));
                    exit;
                }
            }
        }
    } elseif ($action === 'photo-text' || $action === 'photo-move' || $action === 'photo-delete') {
        $group = (string)($_POST['group'] ?? '');
        $index = (int)($_POST['index'] ?? -1);
        $site = load_site();
        if (!isset($site[$group][$index])) {
            $error = 'That picture could not be found.';
        } elseif ($action === 'photo-delete' && $group === 'hero_images' && count($site[$group]) < 2) {
            $error = 'Keep at least one top picture.';
        } elseif ($action === 'photo-delete') {
            $old = (string)$site[$group][$index]['file'];
            array_splice($site[$group], $index, 1);
            if (!save_site($site)) {
                $error = 'Could not remove that picture.';
            } else {
                forget_upload($old);
                $_SESSION['flash'] = 'Picture removed.';
                header('Location: ' . admin_self('view=photos'));
                exit;
            }
        } elseif ($action === 'photo-move') {
            $swap = ($_POST['dir'] ?? '') === 'up' ? $index - 1 : $index + 1;
            if (isset($site[$group][$swap])) {
                $row = $site[$group][$index];
                $site[$group][$index] = $site[$group][$swap];
                $site[$group][$swap] = $row;
                if (!save_site($site)) {
                    $error = 'Could not move that picture.';
                } else {
                    header('Location: ' . admin_self('view=photos'));
                    exit;
                }
            }
        } else {
            $site[$group][$index]['alt'] = clip((string)($_POST['alt'] ?? ''), 180);
            $site[$group][$index]['caption'] = clip((string)($_POST['caption'] ?? ''), 80);
            $site[$group][$index]['wide'] = $group === 'gallery' && !empty($_POST['wide']);
            if ($group === 'gallery' && $site[$group][$index]['alt'] === '') {
                $site[$group][$index]['alt'] = $site[$group][$index]['caption'];
            }
            if (!save_site($site)) {
                $error = 'Could not save that picture.';
            } else {
                $_SESSION['flash'] = 'Picture details saved.';
                header('Location: ' . admin_self('view=photos'));
                exit;
            }
        }
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
                header('Location: ' . admin_self('view=events'));
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
                header('Location: ' . admin_self('view=events'));
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

$view = (string)($_GET['view'] ?? 'home');
if (!in_array($view, ['home', 'text', 'photos', 'events', 'enquiries', 'password'], true)) {
    $view = 'home';
}
if (isset($_GET['edit'])) {
    $view = 'events';
}

$events = read_json('events.json');
usort($events, function ($a, $b) {
    return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
});
$enquiries = array_reverse(read_json('enquiries.json'));
$today = today_string();
$site = load_site();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Admin — Anupriya Productions</title>
  <link rel="icon" href="../assets/img/logo-mark.webp" type="image/webp">
  <link rel="stylesheet" href="../assets/css/style.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/style.css') ?>">
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
      <p class="quiet">After signing in you can change the words, photos, and events on the public page.</p>
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
      <a href="<?= h(admin_self('view=home')) ?>"<?= $view === 'home' ? ' aria-current="page"' : '' ?>>Home</a>
      <a href="<?= h(admin_self('view=text')) ?>"<?= $view === 'text' ? ' aria-current="page"' : '' ?>>Page text</a>
      <a href="<?= h(admin_self('view=photos')) ?>"<?= $view === 'photos' ? ' aria-current="page"' : '' ?>>Photos</a>
      <a href="<?= h(admin_self('view=events')) ?>"<?= $view === 'events' ? ' aria-current="page"' : '' ?>>Events</a>
      <a href="<?= h(admin_self('view=enquiries')) ?>"<?= $view === 'enquiries' ? ' aria-current="page"' : '' ?>>Enquiries</a>
      <a href="<?= h(admin_self('view=password')) ?>"<?= $view === 'password' ? ' aria-current="page"' : '' ?>>Password</a>
      <a href="../index.php">View site</a>
    </nav>
    <h1><?php
      $titles = [
          'home' => 'Dashboard',
          'text' => 'Page text',
          'photos' => 'Photos',
          'enquiries' => 'Enquiries',
          'password' => 'Password',
          'events' => 'Events',
      ];
      echo h($titles[$view]);
    ?></h1>
    <?php if ($flash !== ''): ?><p class="notice" role="status"><?= h($flash) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="alert" role="alert"><?= h($error) ?></p><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <div class="dash-grid">
        <a class="dash-card" href="<?= h(admin_self('view=text')) ?>">
          <strong>Page text</strong>
          <span>Change the name, about section, hosting lines, booking words, and the WhatsApp preview.</span>
        </a>
        <a class="dash-card" href="<?= h(admin_self('view=photos')) ?>">
          <strong>Photos</strong>
          <span>Change the logo, the large top pictures, the about picture, and the gallery.</span>
        </a>
        <a class="dash-card" href="<?= h(admin_self('view=events')) ?>">
          <strong>Events</strong>
          <span>Add a date. Today or later is upcoming. An earlier date is past.</span>
        </a>
        <a class="dash-card" href="<?= h(admin_self('view=enquiries')) ?>">
          <strong>Enquiries</strong>
          <span>Read notes sent from the booking form. <?= count($enquiries) ?> saved.</span>
        </a>
        <a class="dash-card" href="<?= h(admin_self('view=password')) ?>">
          <strong>Password</strong>
          <span>Change the password used to open this dashboard.</span>
        </a>
        <a class="dash-card" href="../index.php">
          <strong>View site</strong>
          <span>Open the public page and check what visitors see.</span>
        </a>
      </div>
    <?php elseif ($view === 'text'): ?>
      <form class="admin-card" method="post" action="<?= h(admin_self('view=text')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save-text">
        <h2>Name and opening</h2>
        <label for="site-name">Site name
          <input id="site-name" name="site_name" type="text" required maxlength="80" value="<?= h($site['site_name']) ?>">
        </label>
        <label for="brand-line">Line under the name
          <input id="brand-line" name="brand_line" type="text" maxlength="80" value="<?= h($site['brand_line']) ?>">
        </label>
        <label for="hero-eyebrow">Small line above the heading
          <input id="hero-eyebrow" name="hero_eyebrow" type="text" maxlength="80" value="<?= h($site['hero_eyebrow']) ?>">
        </label>
        <label for="hero-title">Main heading
          <input id="hero-title" name="hero_title" type="text" required maxlength="80" value="<?= h($site['hero_title']) ?>">
        </label>
        <label for="hero-lede">Sentence under the heading
          <textarea id="hero-lede" name="hero_lede" maxlength="280" rows="3"><?= h($site['hero_lede']) ?></textarea>
        </label>
        <label for="hero-button">Button
          <input id="hero-button" name="hero_button" type="text" maxlength="40" value="<?= h($site['hero_button']) ?>">
        </label>
        <h2>About</h2>
        <label for="about-heading">Heading
          <input id="about-heading" name="about_heading" type="text" maxlength="40" value="<?= h($site['about_heading']) ?>">
        </label>
        <label for="about-1">First paragraph
          <textarea id="about-1" name="about_1" maxlength="800" rows="4"><?= h($site['about_1']) ?></textarea>
        </label>
        <label for="about-2">Second paragraph
          <textarea id="about-2" name="about_2" maxlength="800" rows="3"><?= h($site['about_2']) ?></textarea>
        </label>
        <h2>Hosting</h2>
        <label for="hosting-heading">Heading
          <input id="hosting-heading" name="hosting_heading" type="text" maxlength="40" value="<?= h($site['hosting_heading']) ?>">
        </label>
        <?php for ($i = 0; $i < 4; $i++): ?>
        <?php $service = $site['services'][$i] ?? ['title' => '', 'text' => '']; ?>
        <div class="form-row">
          <label for="service-title-<?= $i ?>">Item <?= $i + 1 ?> title
            <input id="service-title-<?= $i ?>" name="service_title[<?= $i ?>]" type="text" maxlength="80" value="<?= h($service['title']) ?>">
          </label>
          <label for="service-text-<?= $i ?>">Item <?= $i + 1 ?> text
            <input id="service-text-<?= $i ?>" name="service_text[<?= $i ?>]" type="text" maxlength="240" value="<?= h($service['text']) ?>">
          </label>
        </div>
        <?php endfor; ?>
        <h2>Other headings</h2>
        <div class="form-row">
          <label for="events-heading">Events heading
            <input id="events-heading" name="events_heading" type="text" maxlength="40" value="<?= h($site['events_heading']) ?>">
          </label>
          <label for="gallery-heading">Gallery heading
            <input id="gallery-heading" name="gallery_heading" type="text" maxlength="40" value="<?= h($site['gallery_heading']) ?>">
          </label>
        </div>
        <label for="book-title">Booking heading
          <input id="book-title" name="book_title" type="text" maxlength="80" value="<?= h($site['book_title']) ?>">
        </label>
        <label for="book-lead">Booking sentence
          <textarea id="book-lead" name="book_lead" maxlength="280" rows="3"><?= h($site['book_lead']) ?></textarea>
        </label>
        <div class="form-row">
          <label for="footer-name">Footer name
            <input id="footer-name" name="footer_name" type="text" maxlength="80" value="<?= h($site['footer_name']) ?>">
          </label>
          <label for="footer-line">Footer line
            <input id="footer-line" name="footer_line" type="text" maxlength="80" value="<?= h($site['footer_line']) ?>">
          </label>
        </div>
        <h2>Link preview</h2>
        <p class="quiet">This is the title and sentence WhatsApp shows when the page link is shared.</p>
        <label for="share-title">Preview title
          <input id="share-title" name="share_title" type="text" maxlength="140" value="<?= h($site['share_title']) ?>">
        </label>
        <label for="share-text">Preview sentence
          <textarea id="share-text" name="share_text" maxlength="300" rows="3"><?= h($site['share_text']) ?></textarea>
        </label>
        <div class="admin-actions">
          <button class="btn btn-gold" type="submit">Save words</button>
        </div>
      </form>
    <?php elseif ($view === 'photos'): ?>
      <?php
        $slots = [
            'logo_mark' => ['Header logo', 'The small logo at the top of the page.'],
            'logo' => ['Footer logo', 'The large logo at the bottom of the page.'],
            'about_image' => ['About picture', 'The picture beside the About words.'],
        ];
      ?>
      <?php foreach ($slots as $slot => $label): ?>
      <section class="admin-card">
        <h2><?= h($label[0]) ?></h2>
        <p class="quiet"><?= h($label[1]) ?></p>
        <div class="photo-row">
          <img class="photo-fit" src="../<?= h($site[$slot]['file']) ?>" alt="">
          <div>
            <form method="post" action="<?= h(admin_self('view=photos')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="image-label">
              <input type="hidden" name="slot" value="<?= h($slot) ?>">
              <label for="alt-<?= h($slot) ?>">Description
                <input id="alt-<?= h($slot) ?>" name="alt" type="text" maxlength="180" value="<?= h($site[$slot]['alt']) ?>">
              </label>
              <button class="btn btn-line" type="submit">Save description</button>
            </form>
            <form method="post" enctype="multipart/form-data" action="<?= h(admin_self('view=photos')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="replace-image">
              <input type="hidden" name="slot" value="<?= h($slot) ?>">
              <input type="hidden" name="alt" value="<?= h($site[$slot]['alt']) ?>">
              <label for="file-<?= h($slot) ?>">Replace picture
                <input id="file-<?= h($slot) ?>" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
              </label>
              <button class="btn btn-gold" type="submit">Upload</button>
            </form>
          </div>
        </div>
      </section>
      <?php endforeach; ?>

      <section class="admin-card">
        <h2>Top pictures</h2>
        <p class="quiet">These are the large pictures beside the name. The first one is also the phone picture. Add up to 8.</p>
        <?php if (count($site['hero_images']) < 8): ?>
        <form class="add-photo" method="post" enctype="multipart/form-data" action="<?= h(admin_self('view=photos')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add-image">
          <input type="hidden" name="group" value="hero_images">
          <label for="hero-file">Add a picture
            <input id="hero-file" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
          </label>
          <label for="hero-alt">Description
            <input id="hero-alt" name="alt" type="text" maxlength="180" placeholder="What is in the picture">
          </label>
          <button class="btn btn-gold" type="submit">Add picture</button>
        </form>
        <?php endif; ?>
        <?php foreach ($site['hero_images'] as $index => $photo): ?>
        <article class="photo-row">
          <img src="../<?= h($photo['file']) ?>" alt="">
          <div>
            <form method="post" action="<?= h(admin_self('view=photos')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="photo-text">
              <input type="hidden" name="group" value="hero_images">
              <input type="hidden" name="index" value="<?= $index ?>">
              <label for="hero-text-<?= $index ?>">Description
                <input id="hero-text-<?= $index ?>" name="alt" type="text" maxlength="180" value="<?= h($photo['alt']) ?>">
              </label>
              <div class="admin-actions">
                <button class="btn btn-line" type="submit">Save</button>
              </div>
            </form>
            <div class="admin-actions">
              <?php if ($index > 0): ?>
              <form method="post" action="<?= h(admin_self('view=photos')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo-move">
                <input type="hidden" name="group" value="hero_images">
                <input type="hidden" name="index" value="<?= $index ?>">
                <input type="hidden" name="dir" value="up">
                <button class="btn btn-line" type="submit">Move up</button>
              </form>
              <?php endif; ?>
              <?php if ($index < count($site['hero_images']) - 1): ?>
              <form method="post" action="<?= h(admin_self('view=photos')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo-move">
                <input type="hidden" name="group" value="hero_images">
                <input type="hidden" name="index" value="<?= $index ?>">
                <input type="hidden" name="dir" value="down">
                <button class="btn btn-line" type="submit">Move down</button>
              </form>
              <?php endif; ?>
              <?php if (count($site['hero_images']) > 1): ?>
              <form method="post" action="<?= h(admin_self('view=photos')) ?>" data-confirm="Remove this picture?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo-delete">
                <input type="hidden" name="group" value="hero_images">
                <input type="hidden" name="index" value="<?= $index ?>">
                <button class="btn btn-danger" type="submit">Remove</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </section>

      <section class="admin-card">
        <h2>Gallery</h2>
        <p class="quiet">These are the pictures under Gallery. The caption is the small line under each picture.</p>
        <?php if (count($site['gallery']) < 24): ?>
        <form class="add-photo" method="post" enctype="multipart/form-data" action="<?= h(admin_self('view=photos')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add-image">
          <input type="hidden" name="group" value="gallery">
          <label for="gallery-file">Add a picture
            <input id="gallery-file" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
          </label>
          <label for="gallery-caption">Caption
            <input id="gallery-caption" name="caption" type="text" maxlength="80" placeholder="Wedding">
          </label>
          <label class="check-line" for="gallery-wide">
            <input id="gallery-wide" name="wide" type="checkbox" value="1"> Wide picture
          </label>
          <button class="btn btn-gold" type="submit">Add picture</button>
        </form>
        <?php endif; ?>
        <?php foreach ($site['gallery'] as $index => $photo): ?>
        <article class="photo-row">
          <img src="../<?= h($photo['file']) ?>" alt="">
          <div>
            <form method="post" action="<?= h(admin_self('view=photos')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="photo-text">
              <input type="hidden" name="group" value="gallery">
              <input type="hidden" name="index" value="<?= $index ?>">
              <label for="gallery-text-<?= $index ?>">Caption
                <input id="gallery-text-<?= $index ?>" name="caption" type="text" maxlength="80" value="<?= h($photo['caption']) ?>">
              </label>
              <label class="check-line" for="gallery-wide-<?= $index ?>">
                <input id="gallery-wide-<?= $index ?>" name="wide" type="checkbox" value="1"<?= !empty($photo['wide']) ? ' checked' : '' ?>> Wide picture
              </label>
              <div class="admin-actions">
                <button class="btn btn-line" type="submit">Save</button>
              </div>
            </form>
            <div class="admin-actions">
              <?php if ($index > 0): ?>
              <form method="post" action="<?= h(admin_self('view=photos')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo-move">
                <input type="hidden" name="group" value="gallery">
                <input type="hidden" name="index" value="<?= $index ?>">
                <input type="hidden" name="dir" value="up">
                <button class="btn btn-line" type="submit">Move up</button>
              </form>
              <?php endif; ?>
              <?php if ($index < count($site['gallery']) - 1): ?>
              <form method="post" action="<?= h(admin_self('view=photos')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo-move">
                <input type="hidden" name="group" value="gallery">
                <input type="hidden" name="index" value="<?= $index ?>">
                <input type="hidden" name="dir" value="down">
                <button class="btn btn-line" type="submit">Move down</button>
              </form>
              <?php endif; ?>
              <form method="post" action="<?= h(admin_self('view=photos')) ?>" data-confirm="Remove this picture?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="photo-delete">
                <input type="hidden" name="group" value="gallery">
                <input type="hidden" name="index" value="<?= $index ?>">
                <button class="btn btn-danger" type="submit">Remove</button>
              </form>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </section>
    <?php elseif ($view === 'enquiries'): ?>
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
    <?php elseif ($view === 'password'): ?>
      <form class="admin-card" method="post" action="<?= h(admin_self('view=password')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <h2>Change password</h2>
        <label for="current-password">Current password
          <input id="current-password" name="current_password" type="password" required autocomplete="current-password">
        </label>
        <label for="new-password">New password
          <input id="new-password" name="new_password" type="password" required minlength="8" autocomplete="new-password">
        </label>
        <label for="confirm-password">New password again
          <input id="confirm-password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
        </label>
        <div class="admin-actions">
          <button class="btn btn-gold" type="submit">Update password</button>
        </div>
        <p class="quiet">Use at least 8 characters. You stay signed in. The new password is used the next time you sign in.</p>
      </form>
    <?php else: ?>
      <div class="admin-desk">
      <form class="admin-card" method="post" action="<?= h(admin_self('view=events')) ?>">
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
          <?php if ($form['id'] !== ''): ?><a class="btn btn-line" href="<?= h(admin_self('view=events')) ?>">Cancel</a><?php endif; ?>
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
            <form method="post" action="<?= h(admin_self('view=events')) ?>" data-confirm="Remove this event?">
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
