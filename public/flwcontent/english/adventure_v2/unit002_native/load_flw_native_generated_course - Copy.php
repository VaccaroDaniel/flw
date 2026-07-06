<?php
define('CLI_SCRIPT', true);

require_once('C:/Dev/MoodleWindowsInstaller-latest-501/server/moodle/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

global $CFG, $DB, $USER;

if (!is_siteadmin()) {
    $USER = get_admin();
    \core\session\manager::set_user($USER);
}

$unitdir = 'D:/WinPro.Delta/Projects/FLW/FLW-V2/adventure-english-world/unit002';
$webcontentdir = $CFG->dirroot . '/flwcontent/english/adventure_v2/unit002_native';
$webcontenturl = $CFG->wwwroot . '/flwcontent/english/adventure_v2/unit002_native';
$shortname = 'FLW-AEW2-U002-NATIVE';
$fullname = 'Adventure English World V2 - Unit 2 Color Quest';
$coursesummary = '<p>FLW Moodle-native course generated from unit002.</p>';
$targetcategoryid = 43;

function fail_now(string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

function ensure_dir(string $path): void {
    if (!is_dir($path) && !mkdir($path, 0777, true)) {
        fail_now("Could not create directory: {$path}");
    }
}

function safe_remove_dir(string $path, string $allowedparent): void {
    $path = rtrim(normalize_path($path), '/');
    $allowedparent = rtrim(normalize_path($allowedparent), '/') . '/';
    if (strpos($path . '/', $allowedparent) !== 0) {
        fail_now("Refusing to remove outside expected parent: {$path}");
    }
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function copy_file_checked(string $source, string $target): void {
    if (!is_file($source)) {
        fail_now("Missing source file: {$source}");
    }
    ensure_dir(dirname($target));
    if (!copy($source, $target)) {
        fail_now("Could not copy {$source} to {$target}");
    }
}

function copy_dir_checked(string $source, string $target): void {
    ensure_dir($target);
    $sourceprefix = rtrim(normalize_path($source), '/') . '/';
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $relative = substr(normalize_path($item->getPathname()), strlen($sourceprefix));
        if (preg_match('/^load_v2_unit001_.*\.php$/', $relative)) {
            continue;
        }
        $dest = $target . '/' . $relative;
        if ($item->isDir()) {
            ensure_dir($dest);
        } else {
            copy_file_checked($item->getPathname(), $dest);
        }
    }
}

function cdata_text(string $value): string {
    return '<text><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]></text>';
}

function value_first(array $record, array $keys): string {
    foreach ($keys as $key) {
        if (isset($record[$key]) && trim((string)$record[$key]) !== '') {
            return trim((string)$record[$key]);
        }
    }
    return '';
}

function build_quiz_xml_from_csv(string $csvfile, string $targetfile): int {
    $handle = fopen($csvfile, 'r');
    if (!$handle) {
        fail_now("Could not open question bank: {$csvfile}");
    }
    $headers = fgetcsv($handle);
    if (!$headers) {
        fail_now('Question bank CSV has no header row.');
    }
    $headers = array_map(function($header) {
        return strtolower(trim((string)$header));
    }, $headers);
    $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<quiz>'];
    $count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $record = [];
        foreach ($headers as $i => $header) {
            $record[$header] = isset($row[$i]) ? trim((string)$row[$i]) : '';
        }
        $prompt = value_first($record, ['prompt', 'question', 'questiontext']);
        if ($prompt === '') {
            continue;
        }
        $answers = [
            'A' => value_first($record, ['choice_a', 'option_a', 'answer_a', 'a']),
            'B' => value_first($record, ['choice_b', 'option_b', 'answer_b', 'b']),
            'C' => value_first($record, ['choice_c', 'option_c', 'answer_c', 'c']),
        ];
        $correct = strtoupper(value_first($record, ['correct_letter', 'correct']));
        $correctanswer = value_first($record, ['correct_answer', 'answer']);
        if (!isset($answers[$correct])) {
            foreach ($answers as $letter => $answer) {
                if ($correctanswer !== '' && trim($answer) === $correctanswer) {
                    $correct = $letter;
                    break;
                }
            }
        }
        if (!isset($answers[$correct]) || trim($answers[$correct]) === '') {
            continue;
        }
        $qid = value_first($record, ['id', 'qid', 'question_id']);
        if ($qid === '') {
            $qid = 'Q' . ($count + 1);
        }
        $section = value_first($record, ['section', 'section_title']);
        $lesson = value_first($record, ['lesson_title', 'title']);
        $name = trim($qid . ' ' . ($section !== '' ? $section : 'FLW') . ($lesson !== '' ? ' - ' . $lesson : ''));
        $feedback = value_first($record, ['repair_text', 'feedback_hint', 'feedback']);
        if ($feedback === '') {
            $feedback = 'Review the lesson and try again.';
        }

        $xml[] = '<question type="multichoice">';
        $xml[] = '<name>' . cdata_text($name) . '</name>';
        $xml[] = '<questiontext format="html">' . cdata_text($prompt) . '</questiontext>';
        $xml[] = '<generalfeedback format="html">' . cdata_text($feedback) . '</generalfeedback>';
        $xml[] = '<defaultgrade>1.0000000</defaultgrade>';
        $xml[] = '<penalty>0.3333333</penalty>';
        $xml[] = '<hidden>0</hidden>';
        $xml[] = '<idnumber>' . cdata_text($qid) . '</idnumber>';
        $xml[] = '<single>true</single><shuffleanswers>true</shuffleanswers><answernumbering>abc</answernumbering>';
        foreach ($answers as $letter => $answer) {
            if ($answer === '') {
                continue;
            }
            $fraction = ($letter === $correct) ? 100 : 0;
            $xml[] = '<answer fraction="' . $fraction . '" format="html">' . cdata_text($answer)
                . '<feedback format="html">' . cdata_text($fraction === 100 ? 'Correct.' : 'Try again.') . '</feedback></answer>';
        }
        $xml[] = '</question>';
        $count++;
    }
    fclose($handle);
    $xml[] = '</quiz>';
    file_put_contents($targetfile, implode(PHP_EOL, $xml) . PHP_EOL);
    return $count;
}

function extract_required_fragment(string $html, string $pattern, string $label): string {
    if (!preg_match($pattern, $html, $matches)) {
        fail_now("Could not extract HTML fragment: {$label}");
    }
    return $matches[0];
}

function native_css(): string {
    return <<<'CSS'
<style>
.flw-native{--flw-unit-font:"Trebuchet MS",Arial,sans-serif;--flw-teacher-font:Arial,sans-serif;--bg:#fff8ec;--paper:#ffffff;--ink:#203142;--muted:#5f6f7a;--brand:#3b8f54;--brand2:#f2b84b;--accent:#7a5cff;--danger:#c84343;--ok:#2f8a4d;--line:#e6dccb;--shadow:0 10px 28px rgba(64,48,24,.12);--radius:22px;font-family:var(--flw-unit-font);color:var(--ink);line-height:1.55}
.flw-native,.flw-native *,.flw-native button,.flw-native input,.flw-native select,.flw-native textarea{font-family:var(--flw-unit-font)}
.flw-native.teacher-guide,.flw-native.teacher-guide *,.flw-native.teacher-guide button,.flw-native.teacher-guide input,.flw-native.teacher-guide select,.flw-native.teacher-guide textarea{font-family:var(--flw-teacher-font)}
.activity.label.modtype_label,.activity.label.modtype_label .activity-item,.activity.label.modtype_label .activity-altcontent,.activity.label.modtype_label .activity-instance,.activity.label.modtype_label .description,.activity.label.modtype_label .contentwithoutlink,.activity.label.modtype_label .no-overflow{border:0!important;box-shadow:none!important;background:transparent!important}
.activity.label.modtype_label{padding:0!important;margin:0 0 12px!important}
.activity.label.modtype_label .activity-item,.activity.label.modtype_label .activity-altcontent,.activity.label.modtype_label .description,.activity.label.modtype_label .contentwithoutlink,.activity.label.modtype_label .no-overflow{padding:0!important;margin:0!important}
.flw-native *{box-sizing:border-box}
.flw-native a{color:var(--brand);font-weight:700}
.flw-native .header{position:relative;overflow:hidden;background:linear-gradient(135deg,#ffe9b2,#d9f1ff 55%,#ecffe6);border-radius:18px;margin:0 0 14px;border-bottom:6px solid #ffe0a1}
.flw-native .header-inner{padding:28px 18px 34px;display:grid;grid-template-columns:1.1fr .9fr;gap:24px;align-items:center}
.flw-native .header-inner>div:first-child{display:flex;flex-direction:column;justify-content:center}
.flw-native .badge{align-self:flex-start;display:inline-flex;width:auto;max-width:max-content;align-items:center;gap:8px;background:#fff;border:2px solid #ffd980;border-radius:999px;padding:7px 12px;font-weight:800;color:#7a4d00;line-height:1.15;white-space:nowrap;box-shadow:var(--shadow)}
.flw-native h1{font-size:clamp(2rem,5vw,4.6rem);line-height:1.02;margin:.45rem 0 .5rem;color:#1f4f34}
.flw-native h2,.flw-native h3{color:#244e36}
.flw-native .subtitle{font-size:1.2rem;color:#40515e;margin:0 0 1rem}
.flw-native .path{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin:18px 0 0}
.flw-native .path-step{background:#fff;border:2px dashed #ffd87a;border-radius:18px;padding:12px;text-align:center;font-weight:800;color:#624500}
.flw-native .hero-card{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:12px;display:flex;align-items:stretch;transform:rotate(.4deg)}
.flw-native img{max-width:100%;height:auto}
.flw-native .hero-card img,.flw-native .image-card img{display:block;width:100%;border-radius:18px;cursor:zoom-in}
.flw-native .info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}
.flw-native .info-card,.flw-native .course-card,.flw-native .practice-block,.flw-native .ai-box,.flw-native .watch-box,.flw-native .project-box,.flw-native .portfolio-box{background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:18px}
.flw-native .ai-box{border:2px solid #d8ccff;background:#fbf9ff}
.flw-native .ai-box button,.flw-native .practice-actions button,.flw-native .project-button{border:0;background:var(--accent);color:#fff;border-radius:14px;padding:10px 14px;font-weight:900;cursor:pointer}
.flw-native .info-card strong{display:block;color:#1f4f34;font-size:1.05rem}
.flw-native .section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;margin:34px 0 16px}
.flw-native .section-head h2,.flw-native .section-head h3{margin:0}
.flw-native .section-head p{margin:0;color:var(--muted)}
.flw-native .section-head.compact{margin:0 0 14px}
.flw-native .media-content-grid,.flw-native .watch-grid,.flw-native .project-grid{display:grid;grid-template-columns:minmax(280px,.9fr) minmax(320px,1.1fr);gap:18px;align-items:start}
.flw-native .media-stack,.flw-native .content-stack{display:grid;gap:14px}
.flw-native .image-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:10px}
.flw-native ul{margin:.4rem 0 .2rem;padding-left:1.25rem}
.flw-native .text-list{list-style:none;padding-left:0}
.flw-native .text-list li{margin:6px 0}
.flw-native .text-list.keep-bullets{list-style:disc;padding-left:1.85rem}
.flw-native .text-list.keep-bullets li{list-style:disc;margin:6px 0;padding-left:.15rem}
.flw-native .phrase-list{list-style:none!important;list-style-type:none!important;padding-left:0;margin-left:0}
.flw-native .phrase-list li{list-style:none!important;background:#f7fbff;border-left:5px solid #acd8ff;border-radius:12px;padding:8px 10px;margin:6px 0;font-size:1.08rem;font-weight:800}
.flw-native .phrase-list li::marker{content:""!important;font-size:0!important}
.flw-native .word-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.flw-native .word-card{background:#fff;border:2px solid #e8ecd5;border-radius:18px;padding:12px;display:flex;flex-direction:column;gap:4px;min-height:100px;justify-content:center;align-items:center;text-align:center}
.flw-native .word-card strong{font-size:1.35rem;color:#2b5e3b}
.flw-native .word-card small{color:var(--muted)}
.flw-native .mini-audio,.flw-native .audio-chip{border:0;border-radius:999px;background:#e7f7ff;color:#224b62;font-weight:800;padding:8px 12px;cursor:pointer}
.flw-native .audio-chip{background:#f0fff1;border:2px solid #c8edc6;margin:.35rem 0 .5rem}
.flw-native .mini-audio.is-playing,.flw-native .audio-chip.is-playing{background:#fff7d6;border-color:#f1d88e;color:#604700}
.flw-native .can-do{display:inline-block;background:#fffbdc;border:2px solid #f0d573;border-radius:16px;padding:10px 14px;margin:0 0 16px;font-weight:800}
.flw-native .lesson-fold{display:block;border:0;border-radius:18px;overflow:visible;padding:0;margin:0}
.flw-native .lesson-summary{display:none}
.flw-native .practice-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:12px!important}
.flw-native .practice-item{background:#fff;border:1px solid #e8dfce;border-radius:18px;padding:14px;display:flex;flex-direction:column;gap:8px;min-height:0;margin:0}
.flw-native .practice-item.practice-hidden{display:none!important}
.flw-native .practice-head{display:flex;justify-content:space-between;gap:8px;align-items:center}
.flw-native .qid{font-weight:900;color:#315e3f;background:#eef9ee;border-radius:999px;padding:4px 9px;font-size:.85rem}
.flw-native .qtype{font-size:.78rem;color:#6a596b;background:#f4edff;border-radius:999px;padding:4px 8px}
.flw-native .prompt{font-weight:800;margin:.2rem 0}
.flw-native .choices{display:grid;gap:6px;margin-top:auto}
.flw-native .choice{display:flex;gap:8px;align-items:flex-start;background:#fbfbfd;border:1px solid #e8e8ee;border-radius:12px;padding:8px;cursor:pointer;margin:0}
.flw-native .choice input{margin-top:4px}
.flw-native .media-content-grid+.practice-block,.flw-native .watch-grid+.practice-block,.flw-native .project-grid+.practice-block{margin-top:30px}
.flw-native .practice-pager{display:grid;grid-template-columns:auto auto 1fr auto auto;gap:8px;align-items:center;margin-top:16px;border-top:1px solid var(--line);padding-top:14px}
.flw-native .practice-pager button{border:0;border-radius:999px;padding:10px 14px;font-weight:900;cursor:pointer;background:#2f8a4d;color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.08);width:max-content;max-width:100%;justify-self:start}
.flw-native .practice-pager button.secondary{background:#fff;color:#244e36;border:1px solid #d7cdbb}
.flw-native .practice-pager button:disabled{opacity:.45;cursor:not-allowed}
.flw-native .retry-one,.flw-native .show-answer{border:1px solid #c8d6e6;border-radius:12px;background:#fff;padding:8px 10px;font-weight:800;cursor:pointer;width:max-content;max-width:100%;justify-self:start}
.flw-native .practice-page-indicator{text-align:center;font-weight:900;color:#604700;background:#fff7d6;border:1px solid #f1d88e;border-radius:999px;padding:8px 10px;justify-self:stretch}
.flw-native .feedback{min-height:1.4em;margin-top:10px;font-weight:800;border-radius:14px;padding:0}
.flw-native .feedback.ok{background:#ecfff1;border:1px solid #b9e3c2;color:#1e6b35;padding:10px 12px}
.flw-native .feedback.no{background:#fff1ef;border:1px solid #f3b8b1;color:#8a2f25;padding:10px 12px}
.flw-native .video-card{position:relative;background:#111;border-radius:22px;padding:12px;border:1px solid #e1d8c7;box-shadow:var(--shadow)}
.flw-native .video-card video{display:block;width:100%;aspect-ratio:16/9;height:auto;border-radius:16px;background:#000;max-height:520px;object-fit:contain}
.flw-native .video-card .flw-video-shell{position:relative;width:100%;aspect-ratio:16/9;max-height:520px;background:#000;border-radius:16px;overflow:hidden}
.flw-native .video-card .flw-video-shell video{position:absolute;inset:0;width:100%;height:100%;max-height:none;border-radius:0;object-fit:contain}
.flw-native .video-card .flw-video-poster{position:absolute;inset:0;z-index:2;display:flex;align-items:center;justify-content:center;border:0;background:#000;padding:0;margin:0;cursor:pointer}
.flw-native .video-card .flw-video-poster img{display:block;width:100%;height:100%;object-fit:contain}
.flw-native .video-card .flw-video-poster::after{content:"";position:absolute;left:50%;top:50%;transform:translate(-45%,-50%);width:0;height:0;border-top:24px solid transparent;border-bottom:24px solid transparent;border-left:38px solid rgba(255,255,255,.92);filter:drop-shadow(0 2px 8px rgba(0,0,0,.45))}
.flw-native .video-card.is-playing .flw-video-poster,.flw-native .video-card.is-started .flw-video-poster{display:none}
.flw-native .video-caption{font-weight:900;color:#244e36;background:#fff;border-radius:14px;padding:8px 12px;margin-top:10px;text-align:center}
.flw-native .portfolio-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.flw-native .portfolio-item{background:#fff;border:1px solid #eadfcd;border-radius:16px;padding:14px}
.flw-native .lightbox{display:none;position:fixed;z-index:2000;inset:0;background:rgba(0,0,0,.82);align-items:center;justify-content:center;padding:22px}
.flw-native .lightbox img{max-width:95vw;max-height:88vh;border-radius:18px;background:#fff}
.flw-native .lightbox button{position:absolute;top:16px;right:16px;border:0;border-radius:999px;background:#fff;padding:10px 14px;font-weight:900;cursor:pointer}
.flw-native.teacher-guide h1{font-size:1.85rem;line-height:1.2;margin:0 0 1rem}
.flw-native.teacher-guide h2{font-size:1.35rem}
.flw-native.teacher-guide h3{font-size:1.08rem}
@media(max-width:1100px){.flw-native .practice-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:850px){.flw-native .header-inner,.flw-native .media-content-grid,.flw-native .watch-grid,.flw-native .project-grid{grid-template-columns:1fr}.flw-native .info-grid{grid-template-columns:repeat(2,1fr)}.flw-native .path{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.flw-native .practice-grid,.flw-native .info-grid{grid-template-columns:1fr!important}}
</style>
CSS;
}

function native_script(string $assetbaseurl): string {
    global $unitdir;
    $htmlfile = $unitdir . '/index.html';
    $html = file_get_contents($htmlfile);
    if ($html === false) {
        return '';
    }
    $parts = [];
    if (preg_match_all('/<script\b[^>]*src="([^"]+)"[^>]*><\/script>/i', $html, $srcmatches)) {
        foreach ($srcmatches[1] as $src) {
            if (preg_match('/^https?:\/\//i', $src)) {
                continue;
            }
            $local = realpath($unitdir . '/' . $src);
            if ($local && is_file($local) && strpos(normalize_path($local), normalize_path($unitdir) . '/') === 0) {
                $parts[] = PHP_EOL . '/* Source script: ' . basename($src) . ' */' . PHP_EOL . file_get_contents($local);
            }
        }
    }
    if (preg_match_all('/<script\b(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/i', $html, $matches)) {
        $parts[] = implode(PHP_EOL, $matches[1]);
    }
    $body = implode(PHP_EOL, $parts);
    $body = str_replace('const src = `assets/audio/${audioId}.mp3`;', 'const src = `' . $assetbaseurl . '/assets/audio/${audioId}.mp3`;', $body);
    $body = str_replace('"assets/audio/', '"' . $assetbaseurl . '/assets/audio/', $body);
    $body = str_replace("'assets/audio/", "'" . $assetbaseurl . "/assets/audio/", $body);
    $body = str_replace('"assets/video/', '"' . $assetbaseurl . '/assets/video/', $body);
    $body = str_replace("'assets/video/", "'" . $assetbaseurl . "/assets/video/", $body);
    $body = str_replace('>◀ Previous</button>', '>Previous</button>', $body);
    $body = str_replace('>Check page</button>', '>Check Page</button>', $body);
    $body = str_replace('>Reset page</button>', '>Reset Page</button>', $body);
    $body = str_replace('>Next ▶</button>', '>Next Page</button>', $body);
    $body = str_replace('>Next</button>', '>Next Page</button>', $body);
    $body = str_replace('Check the page, then use Next.', 'Check the page, then use Next Page.', $body);
    $body .= <<<'JS'

function initVideoPosters() {
  document.querySelectorAll('.flw-native .video-card video').forEach(video => {
    if (video.dataset.posterReady === '1') return;
    video.dataset.posterReady = '1';
    const card = video.closest('.video-card');
    if (!card) return;
    let shell = video.closest('.flw-video-shell');
    if (!shell) {
      shell = document.createElement('div');
      shell.className = 'flw-video-shell';
      video.parentNode.insertBefore(shell, video);
      shell.appendChild(video);
    }
    const posterSrc = video.getAttribute('poster');
    if (!posterSrc) return;
    const poster = document.createElement('button');
    poster.type = 'button';
    poster.className = 'flw-video-poster';
    poster.setAttribute('aria-label', video.getAttribute('aria-label') || 'Play video');
    const image = document.createElement('img');
    image.src = posterSrc;
    image.alt = '';
    poster.appendChild(image);
    shell.appendChild(poster);
    const hidePoster = () => card.classList.add('is-started', 'is-playing');
    poster.addEventListener('click', () => {
      hidePoster();
      video.play().catch(() => card.classList.remove('is-playing'));
    });
    video.addEventListener('play', hidePoster);
    video.addEventListener('pause', () => card.classList.remove('is-playing'));
    video.addEventListener('ended', () => card.classList.remove('is-playing'));
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initVideoPosters);
} else {
  initVideoPosters();
}
JS;
    return '<script>' . PHP_EOL . $body . PHP_EOL . '</script>';
}

function course_index_label_css(array $labelcmids): string {
    $ids = [];
    foreach ($labelcmids as $label) {
        $ids[] = '#course-index-cm-' . (int)$label['cmid'];
    }
    if (!$ids) {
        return '';
    }
    return PHP_EOL . implode(',', $ids) . '{display:none!important}' . PHP_EOL;
}

function course_index_label_script(array $labelcmids): string {
    $ids = [];
    foreach ($labelcmids as $label) {
        $ids[] = (int)$label['cmid'];
    }
    if (!$ids) {
        return '';
    }
    $jsonids = json_encode($ids);
    return <<<JS

(() => {
  const hiddenLabelCmIds = {$jsonids};
  const tidyCourseIndex = () => {
    hiddenLabelCmIds.forEach((cmid) => {
      const item = document.getElementById(`course-index-cm-\${cmid}`);
      if (item) {
        item.remove();
      }
    });
    document.querySelectorAll('#course-index .courseindex-section').forEach((section) => {
      const content = section.querySelector('.courseindex-sectioncontent');
      if (!content) {
        return;
      }
      if (!content.querySelector('li.courseindex-item')) {
        content.classList.remove('show');
        content.setAttribute('hidden', 'hidden');
        const chevron = section.querySelector('.courseindex-chevron');
        if (chevron) {
          chevron.remove();
        }
      }
    });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tidyCourseIndex);
  } else {
    tidyCourseIndex();
  }
  const observer = new MutationObserver(tidyCourseIndex);
  observer.observe(document.documentElement, {childList: true, subtree: true});
})();
JS;
}

function discover_source_css(string $unitdir): string {
    $files = ['style.css', 'assets/js/style.css', 'assets/css/style.css'];
    $css = [];
    foreach ($files as $file) {
        $path = $unitdir . '/' . $file;
        if (is_file($path)) {
            $css[] = PHP_EOL . '/* Source CSS: ' . $file . ' */' . PHP_EOL . file_get_contents($path);
        }
    }
    return implode(PHP_EOL, $css);
}

function rewrite_fragment_for_native(string $fragment, string $webcontenturl): string {
    $fragment = preg_replace('/<summary class="lesson-title lesson-summary">[\s\S]*?<\/summary>/i', '', $fragment);
    $fragment = preg_replace('/<details\b([^>]*)>/i', '<section$1>', $fragment);
    $fragment = str_replace('</details>', '</section>', $fragment);
    $fragment = preg_replace('/\sopen="open"/i', '', $fragment);
    $fragment = preg_replace('/\sopen=""/i', '', $fragment);
    $fragment = str_replace('src="assets/', 'src="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace('poster="assets/', 'poster="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace('href="assets/', 'href="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace('openImageModal("assets/', 'openImageModal("' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace("openImageModal('assets/", "openImageModal('" . $webcontenturl . "/assets/", $fragment);
    $fragment = str_replace('src="../', 'src="' . $webcontenturl . '/../', $fragment);
    return '<div class="flw-native">' . $fragment . '<div aria-hidden="true" class="image-modal" id="image-modal" onclick="closeImageModal(event)"><button aria-label="Close large image" class="modal-close" onclick="closeImageModal(event)" type="button">x</button><div class="modal-inner"><img alt="" id="modal-img" src=""/><div id="modal-cap"></div></div></div><div aria-modal="true" class="lightbox" onclick="closeLightbox()" role="dialog"><button onclick="closeLightbox()" type="button">Close x</button><img alt="" id="lightbox-img"/></div></div>';
}

function rewrite_teacher_guide_for_native(string $sourcefile, string $webcontenturl): string {
    $raw = file_get_contents($sourcefile);
    if ($raw === false) {
        fail_now("Could not read teacher guide: {$sourcefile}");
    }
    $headextras = '';
    if (preg_match_all('/<link\b[^>]*\bhref=["\']assets\/[^"\']+["\'][^>]*>/i', $raw, $links)) {
        $headextras .= implode(PHP_EOL, $links[0]) . PHP_EOL;
    }
    if (preg_match_all('/<style\b[^>]*>[\s\S]*?<\/style>/i', $raw, $styles)) {
        $headextras .= implode(PHP_EOL, $styles[0]) . PHP_EOL;
    }
    $html = preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $raw, $matches) ? $matches[1] : $raw;
    $html = $headextras . $html;
    $html = str_replace('src="assets/', 'src="' . $webcontenturl . '/assets/', $html);
    $html = str_replace("src='assets/", "src='" . $webcontenturl . "/assets/", $html);
    $html = str_replace('href="assets/', 'href="' . $webcontenturl . '/assets/', $html);
    $html = str_replace("href='assets/", "href='" . $webcontenturl . "/assets/", $html);
    $html = str_replace('poster="assets/', 'poster="' . $webcontenturl . '/assets/', $html);
    $html = str_replace("poster='assets/", "poster='" . $webcontenturl . "/assets/", $html);
    $html = str_replace('openImageModal("assets/', 'openImageModal("' . $webcontenturl . '/assets/', $html);
    $html = str_replace("openImageModal('assets/", "openImageModal('" . $webcontenturl . "/assets/", $html);
    $html = str_replace('url(assets/', 'url(' . $webcontenturl . '/assets/', $html);
    $html = str_replace('url("assets/', 'url("' . $webcontenturl . '/assets/', $html);
    $html = str_replace("url('assets/", "url('" . $webcontenturl . "/assets/", $html);
    return '<div class="flw-native teacher-guide">' . $html . '</div>';
}

function add_lesson_bullet_overrides(string $fragment, int $lesson): string {
    foreach (['Look', 'Try'] as $heading) {
        $fragment = str_replace(
            '<div class="course-card"><h3>' . $heading . '</h3><ul class="text-list">',
            '<div class="course-card"><h3>' . $heading . '</h3><ul class="text-list keep-bullets">',
            $fragment
        );
    }
    return $fragment;
}

function first_match_or_empty(string $html, string $pattern): string {
    return preg_match($pattern, $html, $matches) ? $matches[0] : '';
}

function build_native_sections(string $sourcefile, string $webcontenturl): array {
    $html = file_get_contents($sourcefile);
    if ($html === false) {
        fail_now("Could not read HTML file: {$sourcefile}");
    }

    if (preg_match('/<header class="header" id="top">/i', $html)) {
        $overview = extract_required_fragment($html, '/<header class="header" id="top">[\s\S]*?<details class="lesson lesson-fold" id="lesson-1"/i', 'overview');
        $overview = preg_replace('/<details class="lesson lesson-fold" id="lesson-1"$/i', '', $overview);
        $overview = preg_replace('/<nav class="nav">[\s\S]*?<\/nav>\s*/i', '', $overview);
        $overview = preg_replace('/<main class="container">\s*/i', '', $overview, 1);
        $story = first_match_or_empty($overview, '/<section id="story">[\s\S]*?<\/section>/i');
        if ($story !== '') {
            $overview = str_replace($story, '', $overview);
        }
        $sections = [
            ['section' => 1, 'name' => 'Overview & Unit Map', 'html' => rewrite_fragment_for_native($overview, $webcontenturl)],
            ['section' => 2, 'name' => $story !== '' ? 'Unit Story' : 'Core Language', 'html' => rewrite_fragment_for_native($story !== '' ? $story : '<section class="card"><h2>Core Language</h2></section>', $webcontenturl)],
        ];
        for ($i = 1; $i <= 7; $i++) {
            $fragment = extract_required_fragment($html, '/<details class="lesson lesson-fold" id="lesson-' . $i . '"[^>]*>[\s\S]*?<\/details>/i', 'lesson ' . $i);
            $fragment = add_lesson_bullet_overrides($fragment, $i);
            $title = 'Lesson ' . $i;
            if (preg_match('/<summary[^>]*>[\s\S]*?<h2>(.*?)<\/h2>/i', $fragment, $titlematch)) {
                $title .= ' - ' . trim(strip_tags($titlematch[1]));
            }
            $sections[] = ['section' => $i + 2, 'name' => $title, 'html' => rewrite_fragment_for_native($fragment, $webcontenturl)];
        }
        $watch = extract_required_fragment($html, '/<section class="watch-box" id="watch">[\s\S]*?(?=<section class="project-box" id="project">)/i', 'watch');
        $project = extract_required_fragment($html, '/<section class="project-box" id="project">[\s\S]*?<section class="portfolio-box" id="portfolio">[\s\S]*?<\/section>\s*(?=<\/main>)/i', 'project');
        $sections[] = ['section' => 10, 'name' => 'Watch Lesson', 'html' => rewrite_fragment_for_native($watch, $webcontenturl)];
        $sections[] = ['section' => 11, 'name' => 'Final Project', 'html' => rewrite_fragment_for_native($project, $webcontenturl)];
        return $sections;
    }

    $html = preg_replace('/<nav\b[\s\S]*?<\/nav>\s*/i', '', $html, 1);
    $overview = first_match_or_empty($html, '/<section class="hero">[\s\S]*?<\/section>/i');
    $core = first_match_or_empty($html, '/<section class="card">[\s\S]*?<\/section>/i');
    if ($overview === '') {
        fail_now('Could not extract overview/hero section.');
    }
    $sections = [
        ['section' => 1, 'name' => 'Overview & Unit Map', 'html' => rewrite_fragment_for_native($overview, $webcontenturl)],
        ['section' => 2, 'name' => $core !== '' ? 'Core Language' : 'Unit Story', 'html' => rewrite_fragment_for_native($core !== '' ? $core : '<section class="card"><h2>Unit Story</h2></section>', $webcontenturl)],
    ];

    if (!preg_match_all('/<details class="lesson"[^>]*>[\s\S]*?<\/details>/i', $html, $matches)) {
        fail_now('Could not extract lesson details blocks.');
    }
    $lessonnumber = 1;
    foreach ($matches[0] as $fragment) {
        $summary = '';
        if (preg_match('/<summary[^>]*>([\s\S]*?)<\/summary>/i', $fragment, $summarymatch)) {
            $summary = trim(preg_replace('/\s+/', ' ', strip_tags($summarymatch[1])));
        }
        if (stripos($summary, 'Watch') === 0) {
            $sections[] = ['section' => 10, 'name' => 'Watch Lesson', 'html' => rewrite_fragment_for_native($fragment, $webcontenturl)];
            continue;
        }
        if (stripos($summary, 'Final Project') === 0 || stripos($summary, 'Project') !== false) {
            $sections[] = ['section' => 11, 'name' => 'Final Project', 'html' => rewrite_fragment_for_native($fragment, $webcontenturl)];
            continue;
        }
        if ($lessonnumber <= 7) {
            $title = $summary !== '' ? preg_replace('/^Lesson\s+' . $lessonnumber . '\s*[—-]\s*/u', 'Lesson ' . $lessonnumber . ' - ', $summary) : 'Lesson ' . $lessonnumber;
            if (stripos($title, 'Lesson ' . $lessonnumber) !== 0) {
                $title = 'Lesson ' . $lessonnumber . ' - ' . $title;
            }
            $sections[] = ['section' => $lessonnumber + 2, 'name' => $title, 'html' => rewrite_fragment_for_native($fragment, $webcontenturl)];
            $lessonnumber++;
        }
    }
    return $sections;
}

function update_section_name(stdClass $course, int $sectionnum, string $name): void {
    global $DB;
    course_create_sections_if_missing($course, [$sectionnum]);
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
    $section->name = $name;
    $section->summary = '';
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 1;
    $DB->update_record('course_sections', $section);
}

function add_label_resource(stdClass $course, int $section, string $name, string $html): int {
    global $DB;
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'label';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'label'], MUST_EXIST);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->name = $name;
    $moduleinfo->intro = $html;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    $moduleinfo->completion = 0;
    $moduleinfo->completionview = 0;
    $moduleinfo->completionexpected = 0;
    $moduleinfo->completionunlocked = 1;
    $cm = add_moduleinfo($moduleinfo, $course);
    return (int)$cm->coursemodule;
}

function add_quiz_activity(stdClass $course, int $section, string $name, string $intro): array {
    global $DB;
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section;
    $moduleinfo->name = $name;
    $moduleinfo->intro = $intro;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->timelimit = 0;
    $moduleinfo->overduehandling = 'autosubmit';
    $moduleinfo->graceperiod = 0;
    $moduleinfo->grade = 108;
    $moduleinfo->attempts = 0;
    $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
    $moduleinfo->questionsperpage = 3;
    $moduleinfo->navmethod = QUIZ_NAVMETHOD_FREE;
    $moduleinfo->shuffleanswers = 1;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->browsersecurity = '-';
    $moduleinfo->quizpassword = '';
    $moduleinfo->subnet = '';
    $moduleinfo->allowofflineattempts = 0;
    foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $field) {
        $moduleinfo->{$field . 'during'} = 0;
        $moduleinfo->{$field . 'immediately'} = 1;
        $moduleinfo->{$field . 'open'} = 1;
        $moduleinfo->{$field . 'closed'} = 1;
    }
    $moduleinfo->attemptduring = 1;
    $moduleinfo->overallfeedbackduring = 0;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->groupmode = 0;
    $moduleinfo->groupingid = 0;
    $moduleinfo->completion = 0;
    $moduleinfo->completionview = 0;
    $moduleinfo->completionexpected = 0;
    $moduleinfo->completiongradeitemnumber = null;
    $moduleinfo->completionunlocked = 1;
    $moduleinfo->completionusegrade = 0;
    $moduleinfo->completionpassgrade = 0;
    $moduleinfo->completionattemptsexhausted = 0;
    $moduleinfo->completionminattemptsenabled = 0;
    $moduleinfo->completionminattempts = 0;
    $cm = add_moduleinfo($moduleinfo, $course);
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $quiz->cmid = (int)$cm->coursemodule;
    return ['cmid' => (int)$cm->coursemodule, 'quiz' => $quiz];
}

function import_quiz_xml_to_quiz(stdClass $course, stdClass $quiz, string $xmlfile): array {
    global $DB;
    $context = context_module::instance($quiz->cmid);
    $category = question_get_default_category($context->id, true);
    $qformat = new qformat_xml();
    $qformat->setCategory($category);
    $qformat->setContexts([$context]);
    $qformat->setCourse($course);
    $qformat->setFilename($xmlfile);
    $qformat->setRealfilename(basename($xmlfile));
    $qformat->setMatchgrades('nearest');
    $qformat->setCatfromfile(false);
    $qformat->setContextfromfile(false);
    $qformat->setStoponerror(true);
    $qformat->set_display_progress(false);
    if (!$qformat->importpreprocess() || !$qformat->importprocess() || !$qformat->importpostprocess()) {
        fail_now('Could not import generated Moodle quiz XML.');
    }
    $page = 1;
    foreach ($qformat->questionids as $questionid) {
        quiz_add_quiz_question($questionid, $quiz, $page, 1);
        $page++;
    }
    $questioncount = count($qformat->questionids);
    $DB->set_field('quiz', 'sumgrades', $questioncount, ['id' => $quiz->id]);
    $DB->set_field('quiz', 'grade', $questioncount, ['id' => $quiz->id]);
    return ['categoryid' => (int)$category->id, 'questionids' => array_map('intval', $qformat->questionids)];
}

function enrol_user_with_role(stdClass $course, string $username, string $roleshortname): array {
    global $DB;
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', MUST_EXIST);
    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $plugin = enrol_get_plugin('manual');
    if (!$plugin) {
        fail_now('Manual enrolment plugin is not available.');
    }
    $manualinstance = null;
    foreach (enrol_get_instances($course->id, true) as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }
    if (!$manualinstance) {
        $plugin->add_default_instance($course);
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
    }
    $plugin->enrol_user($manualinstance, $user->id, $role->id, time(), 0, ENROL_USER_ACTIVE);
    return ['username' => $user->username, 'userid' => (int)$user->id, 'role' => $roleshortname];
}

function remove_sections_above(stdClass $course, int $maxsection): void {
    global $DB;
    $sections = $DB->get_records_select('course_sections', 'course = ? AND section > ?', [$course->id, $maxsection], 'section DESC');
    foreach ($sections as $section) {
        course_delete_section($course, (int)$section->section, true, false);
    }
}

function create_test_course_below_100(string $shortname, string $fullname, string $summary): stdClass {
    global $CFG, $DB, $targetcategoryid;
    if ($old = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING)) {
                delete_course($old, false);
        fix_course_sortorder();
    }
    $used = $DB->get_fieldset_select('course', 'id', 'id >= 101', []);
    $used = array_map('intval', $used);
    $targetid = null;
    for ($candidate = 101; $candidate < 10000; $candidate++) {
        if (!in_array($candidate, $used, true)) {
            $targetid = $candidate;
            break;
        }
    }
    if (!$targetid) {
        fail_now('No unused production course id is available.');
    }

    $categoryid = (int)$targetcategoryid;
    if (!$categoryid || !$DB->record_exists('course_categories', ['id' => $categoryid])) {
        $categoryid = (int)$DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        if ($category = $DB->get_record_select('course_categories', $DB->sql_like('name', '?', false), ['English'], 'id', IGNORE_MULTIPLE)) {
            $categoryid = (int)$category->id;
        }
    }
    if (!$categoryid) {
        fail_now('No Moodle course category exists.');
    }

    if ($DB->get_dbfamily() === 'postgres') {
        $DB->get_field_sql("SELECT setval(pg_get_serial_sequence('{$CFG->prefix}course', 'id'), " . ($targetid - 1) . ', true)');
    } else {
        $DB->execute('ALTER TABLE {course} AUTO_INCREMENT = ' . $targetid);
    }

    $course = new stdClass();
    $course->fullname = $fullname;
    $course->shortname = $shortname;
    $course->category = $categoryid;
    $course->summary = $summary;
    $course->summaryformat = FORMAT_HTML;
    $course->format = 'topics';
    $course->numsections = 13;
    $course->startdate = time();
    $course->visible = 1;
    $course = create_course($course);
        $maxid = (int)$DB->get_field_sql('SELECT MAX(id) FROM {course}');
    if ($DB->get_dbfamily() === 'postgres') {
        $DB->get_field_sql("SELECT setval(pg_get_serial_sequence('{$CFG->prefix}course', 'id'), {$maxid}, true)");
    }
    return $course;
}

foreach (['index.html', 'teacher_guide.html'] as $file) {
    if (!is_file($unitdir . '/' . $file)) {
        fail_now("Missing required V2 unit file: {$file}");
    }
}
$questionbank = is_file($unitdir . '/question_bank.csv') ? $unitdir . '/question_bank.csv' : $unitdir . '/data/question_bank.csv';
if (!is_file($questionbank)) {
    fail_now('Missing required question bank CSV at question_bank.csv or data/question_bank.csv');
}

safe_remove_dir($webcontentdir, $CFG->dirroot . '/flwcontent');
copy_dir_checked($unitdir, $webcontentdir);
$quizxml = $webcontentdir . '/generated_moodle_quiz.xml';
$questioncount = build_quiz_xml_from_csv($questionbank, $quizxml);
file_put_contents($webcontentdir . '/flw_native_test.css', preg_replace('/^<style>\s*|\s*<\/style>$/', '', native_css()) . PHP_EOL . discover_source_css($unitdir));
file_put_contents($webcontentdir . '/flw_native_test.js', preg_replace('/^<script>\s*|\s*<\/script>$/', '', native_script($webcontenturl)));
$nativecontent = build_native_sections($unitdir . '/index.html', $webcontenturl);

$course = create_test_course_below_100($shortname, $fullname, $coursesummary);
course_create_sections_if_missing($course, range(0, 13));
remove_sections_above($course, 13);

$createdlabels = [];
foreach ($nativecontent as $content) {
    update_section_name($course, (int)$content['section'], $content['name']);
    $createdlabels[] = [
        'section' => (int)$content['section'],
        'name' => $content['name'],
        'cmid' => add_label_resource($course, (int)$content['section'], $content['name'], $content['html']),
    ];
}

update_section_name($course, 12, 'Quiz');
$quizdata = add_quiz_activity($course, 12, 'Unit 2 Color Quest', '');
$quizimport = import_quiz_xml_to_quiz($course, $quizdata['quiz'], $quizxml);

update_section_name($course, 13, 'Teacher Guide');
$teacherhtml = rewrite_teacher_guide_for_native($unitdir . '/teacher_guide.html', $webcontenturl);
$teachercmid = add_label_resource($course, 13, 'Teacher Guide', $teacherhtml);
set_coursemodule_visible($teachercmid, 0);
$courseindexhidden = $createdlabels;
$courseindexhidden[] = ['cmid' => (int)$quizdata['cmid']];
$courseindexhidden[] = ['cmid' => (int)$teachercmid];
file_put_contents($webcontentdir . '/flw_native_test.css', course_index_label_css($courseindexhidden), FILE_APPEND);
file_put_contents($webcontentdir . '/flw_native_test.js', course_index_label_script($courseindexhidden), FILE_APPEND);
if ($section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 13])) {
    $section->visible = 0;
    $DB->update_record('course_sections', $section);
}
if ($section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0])) {
    $section->summary = '';
    $section->summaryformat = FORMAT_HTML;
    $section->visible = 0;
    $DB->update_record('course_sections', $section);
    $generalcms = $DB->get_fieldset_select('course_modules', 'id', 'course = ? AND section = ?', [$course->id, $section->id]);
    foreach ($generalcms as $cmid) {
        set_coursemodule_visible((int)$cmid, 0);
    }
    if (trim((string)$section->sequence) !== '') {
        foreach (explode(',', $section->sequence) as $cmid) {
            if ((int)$cmid > 0) {
                set_coursemodule_visible((int)$cmid, 0);
            }
        }
    }
}
$announcementcms = $DB->get_fieldset_sql(
    'SELECT cm.id
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module
      WHERE cm.course = ? AND m.name = ?',
    [$course->id, 'forum']
);
foreach ($announcementcms as $cmid) {
    set_coursemodule_visible((int)$cmid, 0);
}

$enrolments = [
    enrol_user_with_role($course, 'win.pro', 'student'),
    enrol_user_with_role($course, 'winpro.delta', 'student'),
    enrol_user_with_role($course, 'admin', 'editingteacher'),
];

rebuild_course_cache($course->id, true);
purge_all_caches();

echo json_encode([
    'courseid' => (int)$course->id,
    'shortname' => $shortname,
    'fullname' => $fullname,
    'conversion' => 'native_label_resources_no_iframe',
    'source' => $unitdir,
    'static_assets_dir' => $webcontentdir,
    'static_assets_url' => $webcontenturl,
    'label_resources' => $createdlabels,
    'quiz_cmid' => $quizdata['cmid'],
    'quiz_question_count' => count($quizimport['questionids']),
    'teacher_guide_cmid' => $teachercmid,
    'enrolments' => $enrolments,
    'courseurl' => $CFG->wwwroot . '/course/view.php?id=' . (int)$course->id,
    'quizurl' => $CFG->wwwroot . '/mod/quiz/view.php?id=' . $quizdata['cmid'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
