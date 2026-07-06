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

$unitdir = 'D:/WinPro.Delta/Projects/FLW/FLW-V2/adventure-english-world/unit010';
$webcontentdir = $CFG->dirroot . '/flwcontent/english/adventure_v2/unit010_native';
$webcontenturl = $CFG->wwwroot . '/flwcontent/english/adventure_v2/unit010_native';
$shortname = 'FLW-AEW2-U010-NATIVE';
$fullname = 'Adventure English World V2 - Unit 10 Family Tree';
$coursesummary = '<p>FLW Moodle-native course generated from unit010.</p>';
$targetcategoryid = 0;

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

function parse_choice_values(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded), function($value) {
            return trim($value) !== '';
        }));
    }
    $values = [];
    if (preg_match_all('/\'((?:\\\\\'|[^\'])*)\'|"((?:\\\\"|[^"])*)"/', $raw, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $value = $match[1] !== '' ? $match[1] : $match[2];
            $value = stripcslashes($value);
            if (trim($value) !== '') {
                $values[] = trim($value);
            }
        }
        return $values;
    }
    $parts = preg_split('/[|,]/', trim($raw, "[] \t\n\r"));
    foreach ($parts as $part) {
        $value = trim($part, " \t\n\r'\"");
        if ($value !== '') {
            $values[] = $value;
        }
    }
    return $values;
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
        $choicevalues = parse_choice_values(value_first($record, ['choices', 'options']));
        if ($choicevalues) {
            $answers = [];
            foreach ($choicevalues as $i => $choicevalue) {
                $answers[chr(65 + $i)] = trim((string)$choicevalue);
            }
        } else {
            $answers = [
                'A' => value_first($record, ['choice_a', 'option_a', 'answer_a', 'a']),
                'B' => value_first($record, ['choice_b', 'option_b', 'answer_b', 'b']),
                'C' => value_first($record, ['choice_c', 'option_c', 'answer_c', 'c']),
                'D' => value_first($record, ['choice_d', 'option_d', 'answer_d', 'd']),
            ];
        }
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
        $answers = array_filter($answers, function($answer) {
            return trim((string)$answer) !== '';
        });
        if (count($answers) < 2 || !isset($answers[$correct])) {
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
.activity.label.modtype_label.flw-native-label,.activity.label.modtype_label.flw-native-label .activity-item,.activity.label.modtype_label.flw-native-label .activity-altcontent,.activity.label.modtype_label.flw-native-label .activity-instance,.activity.label.modtype_label.flw-native-label .description,.activity.label.modtype_label.flw-native-label .contentwithoutlink,.activity.label.modtype_label.flw-native-label .no-overflow{border:0!important;box-shadow:none!important;background:transparent!important}
.activity.label.modtype_label.flw-native-label{padding:0!important;margin:0 0 12px!important}
.activity.label.modtype_label.flw-native-label .activity-item,.activity.label.modtype_label.flw-native-label .activity-altcontent,.activity.label.modtype_label.flw-native-label .description,.activity.label.modtype_label.flw-native-label .contentwithoutlink,.activity.label.modtype_label.flw-native-label .no-overflow{padding:0!important;margin:0!important}
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
    $body = preg_replace(
        '/function\s+audioPlaceholder\s*\(\s*btn\s*\)\s*\{\s*window\.FLW_playAudioFile\s*\(\s*btn\s*\)\s*;\s*\},\s*\{transform:\s*[\s\S]*?\}\],\s*\{duration:\s*450\}\s*\);\s*\}/',
        'function audioPlaceholder(btn){ window.FLW_playAudioFile(btn); }',
        $body
    );
    $body = str_replace(
        'const saved = JSON.parse(localStorage.getItem(STORE_KEY) || "{}");',
        'const saved = (() => { try { return JSON.parse(localStorage.getItem(STORE_KEY) || "{}"); } catch (error) { console.warn("FLW progress store reset.", error); localStorage.removeItem(STORE_KEY); return {}; } })();',
        $body
    );
    $body = str_replace(
        'localStorage.setItem(STORE_KEY, JSON.stringify(state));',
        'try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch (error) { console.warn("FLW progress store unavailable.", error); }',
        $body
    );
    $body = str_replace(
        "function setupModal(){ const modal=document.querySelector('.modal'); const img=modal.querySelector('img'); const title=modal.querySelector('.modal-title');",
        "function setupModal(){ const modal=document.querySelector('.modal'); if(!modal) return; const img=modal.querySelector('img'); const title=modal.querySelector('.modal-title');",
        $body
    );
$body .= <<<'JS'

function initNativeActivityScope() {
  document.querySelectorAll('.flw-native').forEach(root => {
    const activity = root.closest('.activity.label.modtype_label');
    if (activity) {
      activity.classList.add('flw-native-label');
      if (root.classList.contains('teacher-guide')) {
        activity.classList.add('flw-native-teacher-guide-label');
      }
    }
    const section = root.closest('.course-section');
    if (section) {
      section.classList.add('flw-native-course-section');
      if (root.classList.contains('teacher-guide')) {
        section.classList.add('flw-native-teacher-guide-section');
      }
    }
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNativeActivityScope);
} else {
  initNativeActivityScope();
}

function initNativeImageZoom() {
  const ensureNativeImageModal = () => {
    let modal = document.getElementById('flw-native-image-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'flw-native-image-modal';
    modal.className = 'flw-native image-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = '<button aria-label="Close large image" class="modal-close" type="button"><span class="close-icon" aria-hidden="true"><span class="close-line close-line-a"></span><span class="close-line close-line-b"></span></span></button><div class="modal-inner"><img alt="" id="flw-native-modal-img" src=""/><div id="flw-native-modal-cap"></div></div>';
    document.body.appendChild(modal);
    modal.style.cssText = 'position:fixed!important;inset:0!important;z-index:2147483000!important;background:rgba(0,0,0,.82)!important;display:none;align-items:center!important;justify-content:center!important;padding:22px!important;box-sizing:border-box!important;';
    const inner = modal.querySelector('.modal-inner');
    if (inner) inner.style.cssText = 'position:relative!important;z-index:2147483001!important;max-width:min(96vw,1300px)!important;max-height:94vh!important;background:#fff!important;border-radius:20px!important;padding:12px!important;box-sizing:border-box!important;';
    const closeButton = modal.querySelector('.modal-close');
    if (closeButton) {
      closeButton.style.cssText = 'position:fixed!important;right:18px!important;top:12px!important;z-index:2147483002!important;width:46px!important;height:46px!important;min-width:46px!important;min-height:46px!important;padding:0!important;border:2px solid #fff!important;background:#b42318!important;color:#fff!important;border-radius:999px!important;display:grid!important;place-items:center!important;font-size:0!important;line-height:1!important;box-shadow:0 6px 18px rgba(0,0,0,.32)!important;cursor:pointer!important;';
      const closeIcon = closeButton.querySelector('.close-icon');
      if (closeIcon) closeIcon.style.cssText = 'position:relative!important;display:block!important;width:20px!important;height:20px!important;';
      closeButton.querySelectorAll('.close-line').forEach(line => {
        line.style.cssText = 'position:absolute!important;left:50%!important;top:50%!important;width:22px!important;height:3px!important;background:#fff!important;border-radius:999px!important;transform-origin:center!important;';
      });
      const lineA = closeButton.querySelector('.close-line-a');
      const lineB = closeButton.querySelector('.close-line-b');
      if (lineA) lineA.style.transform = 'translate(-50%,-50%) rotate(45deg)';
      if (lineB) lineB.style.transform = 'translate(-50%,-50%) rotate(-45deg)';
    }
    const modalImage = modal.querySelector('#flw-native-modal-img');
    if (modalImage) modalImage.style.cssText = 'max-width:100%!important;max-height:82vh!important;display:block!important;border-radius:12px!important;object-fit:contain!important;';
    const close = event => {
      if (event && event.type === 'click') {
        const target = event.target;
        if (!target?.closest?.('.modal-close, .close-modal')) return;
      }
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      modal.style.display = 'none';
    };
    modal.querySelector('.modal-close').addEventListener('click', close);
    return modal;
  };
  window.openImageModal = (src, alt) => {
    const modal = ensureNativeImageModal();
    const image = modal.querySelector('#flw-native-modal-img');
    const caption = modal.querySelector('#flw-native-modal-cap');
    if (!image) return;
    document.querySelectorAll('body > .image-modal:not(#flw-native-image-modal), body > .modal.open:not(#flw-native-image-modal)').forEach(oldModal => {
      oldModal.hidden = true;
      oldModal.classList.remove('open');
      oldModal.setAttribute('aria-hidden', 'true');
      oldModal.style.display = 'none';
    });
    image.src = src || '';
    image.alt = alt || 'large lesson image';
    if (caption) caption.textContent = alt || '';
    modal.hidden = false;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    modal.style.display = 'flex';
  };
  window.closeImageModal = event => {
    const modal = ensureNativeImageModal();
    if (event && event.type === 'click') {
      const target = event.target;
      if (!target?.closest?.('.modal-close, .close-modal')) return;
    }
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    modal.style.display = 'none';
  };
  window.openModal = (src, alt) => window.openImageModal(src, alt);
  window.openImage = (src, alt) => window.openImageModal(src, alt);
  window.closeModal = event => window.closeImageModal(event);
  if (document.documentElement.dataset.nativeZoomDelegationReady !== '1') {
    document.documentElement.dataset.nativeZoomDelegationReady = '1';
    document.addEventListener('click', event => {
      const btn = event.target?.closest?.('.flw-native .zoom-btn, .flw-native .zoom[data-img]');
      if (!btn) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      const image = btn.closest('.img-box, figure, .image-card, .hero-card')?.querySelector('img');
      const src = btn.dataset.img || image?.currentSrc || image?.src || '';
      const alt = btn.dataset.alt || btn.dataset.title || image?.alt || btn.getAttribute('aria-label') || '';
      if (src) window.openImageModal(src, alt);
    }, true);
  }
  document.querySelectorAll('.flw-native .zoom-btn').forEach(btn => {
    if (btn.dataset.nativeZoomReady === '1') return;
    btn.dataset.nativeZoomReady = '1';
    btn.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const image = btn.closest('.img-box, figure, .image-card, .hero-card')?.querySelector('img');
      const src = btn.dataset.img || image?.currentSrc || image?.src || '';
      const alt = btn.dataset.alt || btn.dataset.title || image?.alt || btn.getAttribute('aria-label') || '';
      if (src) window.openImageModal(src, alt);
    });
  });
  document.querySelectorAll('.flw-native .zoom[data-img]').forEach(btn => {
    if (btn.dataset.nativeZoomReady === '1') return;
    btn.dataset.nativeZoomReady = '1';
    btn.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const modal = ensureNativeImageModal();
      let image = modal.querySelector('#flw-native-modal-img');
      let caption = modal.querySelector('#flw-native-modal-cap');
      if (!modal || !image) return;
      document.querySelectorAll('body > .image-modal:not(#flw-native-image-modal), body > .modal.open:not(#flw-native-image-modal)').forEach(oldModal => {
        oldModal.hidden = true;
        oldModal.classList.remove('open');
        oldModal.setAttribute('aria-hidden', 'true');
        oldModal.style.display = 'none';
      });
      image.src = btn.dataset.img || '';
      image.alt = btn.dataset.title || btn.getAttribute('aria-label') || '';
      if (caption) caption.textContent = btn.dataset.title || '';
      modal.hidden = false;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      modal.style.display = 'flex';
    });
  });
  document.querySelectorAll('.flw-native .image-modal, .flw-native .modal').forEach(modal => {
    if (modal.dataset.nativeCloseReady === '1') return;
    modal.dataset.nativeCloseReady = '1';
    const close = () => {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      modal.style.display = 'none';
    };
    modal.querySelectorAll('.modal-close, .close-modal').forEach(btn => {
      btn.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        close();
      });
    });
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNativeImageZoom);
} else {
  initNativeImageZoom();
}
window.addEventListener('load', initNativeImageZoom);
setTimeout(initNativeImageZoom, 250);
setTimeout(initNativeImageZoom, 1000);

function findNativeLessonSummary(target) {
  const start = target?.nodeType === 1 ? target : target?.parentElement;
  const summary = start?.closest?.('summary');
  if (!summary || summary.parentElement?.matches?.('.flw-native section.lesson, .flw-native details.lesson, .flw-native section.project-block, .flw-native details.project-block') !== true) return null;
  return summary;
}
function findNativeLessonBody(lesson) {
  return Array.from(lesson.children).find(child => child.classList?.contains('lesson-body') || child.classList?.contains('project-body')) || null;
}
function syncNativeLessonSummary(summary) {
  const lesson = summary.parentElement;
  const body = lesson ? findNativeLessonBody(lesson) : null;
  if (!lesson || !body) return;
  const collapsed = lesson.classList.contains('is-collapsed');
  summary.setAttribute('role', 'button');
  summary.setAttribute('tabindex', '0');
  summary.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  if (lesson.tagName && lesson.tagName.toLowerCase() === 'details') {
    lesson.open = !collapsed;
  }
  const symbol = summary.querySelector('.plus, .fold-symbol');
  if (symbol) {
    if (symbol.classList.contains('fold-symbol')) {
      symbol.textContent = collapsed ? '+' : '−';
    } else {
      symbol.textContent = '+';
    }
    symbol.setAttribute('aria-hidden', 'true');
  }
  body.style.display = collapsed ? 'none' : '';
}
function toggleNativeLessonSummary(summary, event) {
  const lesson = summary.parentElement;
  const body = lesson ? findNativeLessonBody(lesson) : null;
  if (!lesson || !body) return;
  event?.preventDefault();
  event?.stopPropagation();
  lesson.classList.toggle('is-collapsed');
  syncNativeLessonSummary(summary);
}
function initNativeLessonSummaries() {
  document.querySelectorAll('.flw-native section.lesson > summary, .flw-native details.lesson > summary, .flw-native section.project-block > summary, .flw-native details.project-block > summary').forEach(summary => {
    if (summary.dataset.nativeSummaryReady !== '1') {
      summary.dataset.nativeSummaryReady = '1';
      summary.addEventListener('click', event => toggleNativeLessonSummary(summary, event));
      summary.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') toggleNativeLessonSummary(summary, event);
      });
    }
    syncNativeLessonSummary(summary);
  });
  if (document.documentElement.dataset.nativeLessonDelegationReady === '1') return;
  document.documentElement.dataset.nativeLessonDelegationReady = '1';
  document.addEventListener('click', event => {
    const summary = findNativeLessonSummary(event.target);
    if (summary) toggleNativeLessonSummary(summary, event);
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    const summary = findNativeLessonSummary(event.target);
    if (summary) toggleNativeLessonSummary(summary, event);
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNativeLessonSummaries);
} else {
  initNativeLessonSummaries();
}
window.addEventListener('load', initNativeLessonSummaries);
setTimeout(initNativeLessonSummaries, 250);
setTimeout(initNativeLessonSummaries, 1000);

function initVideoPosters() {
  document.querySelectorAll('.flw-native .video-card video').forEach(video => {
    if (video.dataset.posterReady === '1') return;
    video.dataset.posterReady = '1';
    const card = video.closest('.video-card');
    if (!card) return;
    const posterSrc = video.getAttribute('poster');
    if (!posterSrc) return;
    let shell = video.closest('.flw-video-shell');
    if (!shell) {
      shell = document.createElement('div');
      shell.className = 'flw-video-shell';
      video.parentNode.insertBefore(shell, video);
      shell.appendChild(video);
    }
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

function initNativePracticeBlocks() {
  if (typeof initPractice !== 'function') return;
  document.querySelectorAll('.flw-native .practice[id^="practice-"]').forEach(root => {
    const sectionId = root.id.replace(/^practice-/, '');
    if (!sectionId) return;
    if (root.children.length === 0 || !root.querySelector('.practice-header')) {
      try {
        initPractice(sectionId);
      } catch (error) {
        console.warn('FLW Auto Check render failed for', sectionId, error);
        delete root.dataset.nativePracticeRendered;
        return;
      }
    }
    if (root.querySelector('.practice-header')) root.dataset.nativePracticeRendered = '1';
  });
  if (typeof updateOverallProgress === 'function') {
    updateOverallProgress();
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNativePracticeBlocks);
} else {
  initNativePracticeBlocks();
}
setTimeout(initNativePracticeBlocks, 250);
setTimeout(initNativePracticeBlocks, 1000);
setTimeout(initNativePracticeBlocks, 2000);
window.addEventListener('load', initNativePracticeBlocks);
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

function scope_css_selectors_for_native(string $selector): string {
    $parts = explode(',', $selector);
    $scoped = [];
    foreach ($parts as $part) {
        $s = trim($part);
        if ($s === '') {
            continue;
        }
        if (stripos($s, '.flw-native') === 0) {
            $scoped[] = $s;
        } else if (stripos($s, ':root') === 0) {
            $scoped[] = preg_replace('/^:root\b/i', '.flw-native', $s, 1);
        } else if (preg_match('/^html\b/i', $s)) {
            $scoped[] = preg_replace('/^html\b/i', '.flw-native', $s, 1);
        } else if (preg_match('/^body\b/i', $s)) {
            $scoped[] = preg_replace('/^body\b/i', '.flw-native', $s, 1);
        } else {
            $scoped[] = '.flw-native ' . $s;
        }
    }
    return implode(',', $scoped);
}

function scope_css_for_native(string $css): string {
    $out = '';
    $pos = 0;
    $len = strlen($css);
    while (($open = strpos($css, '{', $pos)) !== false) {
        $selector = substr($css, $pos, $open - $pos);
        $depth = 1;
        $i = $open + 1;
        while ($i < $len && $depth > 0) {
            $char = $css[$i];
            if ($char === '{') {
                $depth++;
            } else if ($char === '}') {
                $depth--;
            }
            $i++;
        }
        if ($depth !== 0) {
            $out .= substr($css, $pos);
            return $out;
        }
        $body = substr($css, $open + 1, $i - $open - 2);
        $trimmed = trim($selector);
        if ($trimmed === '') {
            $out .= $selector . '{' . $body . '}';
        } else if (preg_match('/^@(media|supports|container)\b/i', $trimmed)) {
            $out .= $selector . '{' . scope_css_for_native($body) . '}';
        } else if (preg_match('/^@(keyframes|font-face|page|property)\b/i', $trimmed)) {
            $out .= $selector . '{' . $body . '}';
        } else if (strpos($trimmed, '@') === 0) {
            $out .= $selector . '{' . $body . '}';
        } else {
            $out .= scope_css_selectors_for_native($selector) . '{' . $body . '}';
        }
        $pos = $i;
    }
    $out .= substr($css, $pos);
    return $out;
}

function discover_source_css(string $unitdir): string {
    $files = ['style.css', 'assets/js/style.css', 'assets/css/style.css'];
    $css = [];
    $seen = [];
    foreach ($files as $file) {
        $path = $unitdir . '/' . $file;
        if (is_file($path)) {
            $seen[normalize_path(realpath($path))] = true;
            $css[] = PHP_EOL . '/* Source CSS: ' . $file . ' */' . PHP_EOL . scope_css_for_native(file_get_contents($path));
        }
    }
    $index = $unitdir . '/index.html';
    if (is_file($index)) {
        $html = file_get_contents($index);
        if ($html !== false) {
            if (preg_match_all('/<link\b[^>]*\bhref=["\']([^"\']+\.css(?:\?[^"\']*)?)["\'][^>]*>/i', $html, $links)) {
                foreach ($links[1] as $href) {
                    if (preg_match('/^https?:\/\//i', $href)) {
                        continue;
                    }
                    $relative = preg_replace('/\?.*$/', '', html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
                    $path = realpath($unitdir . '/' . $relative);
                    if ($path && is_file($path) && strpos(normalize_path($path), normalize_path($unitdir) . '/') === 0) {
                        $key = normalize_path($path);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $css[] = PHP_EOL . '/* Source CSS: ' . $relative . ' */' . PHP_EOL . scope_css_for_native(file_get_contents($path));
                        }
                    }
                }
            }
            if (preg_match_all('/<style\b[^>]*>([\s\S]*?)<\/style>/i', $html, $matches)) {
                foreach ($matches[1] as $idx => $style) {
                    $css[] = PHP_EOL . '/* Source inline CSS: index.html #' . ($idx + 1) . ' */' . PHP_EOL . scope_css_for_native($style);
                }
            }
        }
    }
    return implode(PHP_EOL, $css);
}

function native_section_css(string $rootid, string $unitdir): string {
    $css = preg_replace('/^<style>\s*|\s*<\/style>$/', '', native_css());
    $css .= PHP_EOL . discover_source_css($unitdir);
    $css .= PHP_EOL . native_conversion_compat_css();
    $safeid = preg_replace('/[^A-Za-z0-9_-]/', '', $rootid);
    if ($safeid === '') {
        return $css;
    }
    return preg_replace('/(?<![A-Za-z0-9_-])\.flw-native\b/', '#' . $safeid . '.flw-native', $css);
}

function native_section_script(string $webcontenturl): string {
    $src = htmlspecialchars($webcontenturl . '/flw_native_test.js', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<script>(function(){var src="' . $src . '";if(window.FLWNativeSectionScriptLoaded){return;}window.FLWNativeSectionScriptLoaded=true;var script=document.createElement("script");script.src=src;script.defer=false;document.head.appendChild(script);})();</script>';
}

function native_section_root_id(int $section, string $name): string {
    return 'flw-native-section-' . $section . '-' . substr(md5($name), 0, 10);
}

function native_conversion_compat_css(): string {
    return <<<'CSS'

/* Native conversion compatibility */
body#page-course-view-topics.path-course-view .course-content .course-section .course-section-header,
body#page-course-view-topics.path-course-view .course-content .course-section .sectionname,
body#page-course-view-topics.path-course-view .course-content .course-section .section_action_menu,
body#page-course-view-topics.path-course-view .course-content .course-section .sectionbadges {
    display:none!important;
}
body#page-course-view-topics.path-course-view .course-content .course-section {
    padding-top:0!important;
}
body#page-course-view-topics.path-course-view .course-content .course-section .content {
    margin-top:0!important;
}
body#page-course-view-topics.path-course-view #page-content,
body#page-course-view-topics.path-course-view #region-main-box,
body#page-course-view-topics.path-course-view #region-main,
body#page-course-view-topics.path-course-view div[role="main"],
body#page-course-view-topics.path-course-view .main-inner,
body#page-course-view-topics.path-course-view .container,
body#page-course-view-topics.path-course-view .container-fluid,
body#page-course-view-topics.path-course-view .course-content,
body#page-course-view-topics.path-course-view .course-content .topics,
body#page-course-view-topics.path-course-view .course-content ul.topics,
body#page-course-view-topics.path-course-view .course-section,
body#page-course-view-topics.path-course-view .course-section .section-item,
body#page-course-view-topics.path-course-view .course-section .content,
body#page-course-view-topics.path-course-view .course-section .summary,
body#page-course-view-topics.path-course-view .activity,
body#page-course-view-topics.path-course-view .activity-item,
body#page-course-view-topics.path-course-view .activity-altcontent,
body#page-course-view-topics.path-course-view .description,
body#page-course-view-topics.path-course-view .contentwithoutlink,
body#page-course-view-topics.path-course-view .no-overflow {
    max-width:none!important;
}
body#page-course-view-topics.path-course-view .main-inner,
body#page-course-view-topics.path-course-view #region-main,
body#page-course-view-topics.path-course-view .course-content {
    width:100%!important;
}
body#page-course-view-topics.path-course-view.limitedwidth #page.drawers .main-inner,
body#page-course-view-topics.path-course-view #page.drawers .main-inner,
body#page-course-view-topics.path-course-view #page-content,
body#page-course-view-topics.path-course-view #region-main {
    padding-left:.5rem!important;
    padding-right:.5rem!important;
}
body.path-course-view .navbar.fixed-top #usernavigation,
body.path-course-view .navbar.fixed-top #usernavigation .usermenu,
body.path-course-view .navbar.fixed-top #usernavigation .dropdown,
body.path-course-view .navbar.fixed-top #usernavigation .userbutton,
body.path-course-view .navbar.fixed-top #usernavigation .btn,
body.path-course-view .navbar.fixed-top #usernavigation .nav-link,
body.path-course-view .navbar.fixed-top #usernavigation .nav-link.active,
body.path-course-view .navbar.fixed-top #usernavigation .nav-link:active,
body.path-course-view .navbar.fixed-top #usernavigation .nav-link:focus,
body.path-course-view .navbar.fixed-top #usernavigation .nav-link:hover,
body.path-course-view .navbar.fixed-top #usernavigation .dropdown-toggle,
body.path-course-view .navbar.fixed-top #usernavigation .dropdown-toggle:active,
body.path-course-view .navbar.fixed-top #usernavigation .dropdown-toggle:focus,
body.path-course-view .navbar.fixed-top #usernavigation .dropdown-toggle:hover,
body.path-course-view .navbar.fixed-top #user-menu-toggle,
body.path-course-view .navbar.fixed-top #user-menu-toggle:active,
body.path-course-view .navbar.fixed-top #user-menu-toggle:focus,
body.path-course-view .navbar.fixed-top #user-menu-toggle:hover,
body.path-course-view .navbar.fixed-top #user-menu-toggle[aria-expanded="true"] {
    background:transparent!important;
    background-color:transparent!important;
    box-shadow:none!important;
}
body.path-course-view .navbar.fixed-top #usernavigation .userinitials {
    background-color:#e9ecef!important;
    color:#344054!important;
}
body#page-course-view-topics.path-course-view .course-content .topics,
body#page-course-view-topics.path-course-view .course-content ul.topics,
body#page-course-view-topics.path-course-view .course-section .section-item,
body#page-course-view-topics.path-course-view .course-section .content {
    padding-left:0!important;
    padding-right:0!important;
}
body#page-course-view-topics.path-course-view .activity.label.modtype_label.flw-native-label {
    margin:0!important;
}
body#page-course-view-topics.path-course-view .flw-native {
    width:100%!important;
}
body#page-course-view-topics.path-course-view .flw-native > section.card,
body#page-course-view-topics.path-course-view .flw-native > article.card,
body#page-course-view-topics.path-course-view .flw-native > details.card {
    background:var(--card,#fff)!important;
    border:2px solid var(--line,#e7d5ac)!important;
    border-radius:22px!important;
    box-shadow:0 5px 16px rgba(0,0,0,.06)!important;
    padding:18px!important;
    margin:16px 0!important;
    overflow:visible!important;
}
body#page-course-view-topics.path-course-view .flw-native section.section,
body#page-course-view-topics.path-course-view .flw-native article.section,
body#page-course-view-topics.path-course-view .flw-native details.section {
    background:var(--aw-card,#fff)!important;
    border:2px solid var(--aw-line,#ecd493)!important;
    border-radius:22px!important;
    padding:18px!important;
    margin:18px 0!important;
    box-shadow:0 8px 20px rgba(105,79,26,.08)!important;
    overflow:visible!important;
}
body#page-course-view-topics.path-course-view .flw-native section.section > h2:first-child,
body#page-course-view-topics.path-course-view .flw-native article.section > h2:first-child {
    margin-top:0!important;
}
body#page-course-view-topics.path-course-view .flw-native section.section > summary:first-child,
body#page-course-view-topics.path-course-view .flw-native details.section > summary:first-child {
    display:block!important;
    margin:0 0 14px!important;
    padding:8px 0!important;
    color:#386d2d!important;
    font-size:1.45rem!important;
    font-weight:900!important;
    line-height:1.2!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson {
    background:var(--card,#fff)!important;
    border:2px solid var(--line,#e7d5ac)!important;
    border-radius:22px!important;
    margin:18px 0!important;
    padding:0!important;
    overflow:hidden!important;
    box-shadow:var(--shadow,0 10px 24px rgba(72,45,10,.12))!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > summary:first-child {
    cursor:pointer!important;
    list-style:none!important;
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    gap:14px!important;
    margin:0!important;
    padding:18px 22px!important;
    background:linear-gradient(90deg,#fff,#fff2cc)!important;
    color:var(--ink,#243048)!important;
    border-radius:20px 20px 0 0!important;
    font-size:1.45rem!important;
    font-weight:900!important;
    line-height:1.25!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson.is-collapsed > .lesson-body {
    display:none!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > summary:first-child .plus,
body#page-course-view-topics.path-course-view .flw-native section.lesson > summary:first-child .fold-symbol,
body#page-course-view-topics.path-course-view .flw-native section.project-block > summary:first-child .plus,
body#page-course-view-topics.path-course-view .flw-native section.project-block > summary:first-child .fold-symbol {
    order:2!important;
    margin-left:auto!important;
    flex:0 0 34px!important;
    width:34px!important;
    height:34px!important;
    min-width:34px!important;
    min-height:34px!important;
    padding:0!important;
    border-radius:999px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    line-height:1!important;
    aspect-ratio:1/1!important;
    box-sizing:border-box!important;
    transition:transform .16s ease!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson:not(.is-collapsed) > summary:first-child .plus {
    transform:rotate(45deg)!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > summary:first-child .fold-symbol,
body#page-course-view-topics.path-course-view .flw-native section.project-block > summary:first-child .fold-symbol {
    transform:none!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > summary:first-child::-webkit-details-marker {
    display:none!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson .lesson-body {
    padding:18px 22px!important;
}
body#page-course-view-topics.path-course-view .flw-native video,
body#page-course-view-topics.path-course-view .flw-native .watch-video {
    display:block!important;
    width:100%!important;
    max-width:900px!important;
    height:auto!important;
    margin:0 auto 16px!important;
    border-radius:20px!important;
    background:#000!important;
    object-fit:contain!important;
    accent-color:initial!important;
}
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-panel,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-timeline,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-play-button,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-current-time-display,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-time-remaining-display,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-mute-button,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-volume-slider,
body#page-course-view-topics.path-course-view .flw-native video::-webkit-media-controls-fullscreen-button {
    font:revert!important;
    color:revert!important;
    background:revert!important;
    border:revert!important;
    box-shadow:revert!important;
    padding:revert!important;
    margin:revert!important;
    width:revert!important;
    height:revert!important;
    min-width:revert!important;
    min-height:revert!important;
    border-radius:revert!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card,
body#page-course-view-topics.path-course-view .flw-native details.lesson > .lesson-body.video-card {
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
    color:var(--ink,#243048)!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card > p,
body#page-course-view-topics.path-course-view .flw-native details.lesson > .lesson-body.video-card > p {
    color:var(--ink,#243048)!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card .watch-video,
body#page-course-view-topics.path-course-view .flw-native details.lesson > .lesson-body.video-card .watch-video {
    background:#111!important;
    border-radius:18px!important;
    overflow:hidden!important;
    box-shadow:0 4px 14px rgba(0,0,0,.15)!important;
}
body#page-course-view-topics.path-course-view .flw-native .hero {
    grid-template-columns:minmax(260px,.9fr) minmax(300px,1.1fr);
    gap:16px;
}
body#page-course-view-topics.path-course-view .flw-native .hero-copy,
body#page-course-view-topics.path-course-view .flw-native .hero-card,
body#page-course-view-topics.path-course-view .flw-native .hero-img {
    min-width:0;
}
body#page-course-view-topics.path-course-view .flw-native .eyebrow,
body#page-course-view-topics.path-course-view .flw-native .badge {
    max-width:100%;
}
body#page-course-view-topics.path-course-view .flw-native .hero .badge {
    font-size:1rem!important;
    line-height:1.2!important;
    padding:8px 14px!important;
}
body#page-course-view-topics.path-course-view .flw-native .hero .meta .badge {
    font-size:.98rem!important;
    line-height:1.2!important;
}
body#page-course-view-topics.path-course-view .flw-native .practice,
body#page-course-view-topics.path-course-view .flw-native .q-list,
body#page-course-view-topics.path-course-view .flw-native .lesson-grid,
body#page-course-view-topics.path-course-view .flw-native .core-language,
body#page-course-view-topics.path-course-view .flw-native .watch-grid,
body#page-course-view-topics.path-course-view .flw-native .project-grid {
    width:100%;
    max-width:none;
}
body#page-course-view-topics.path-course-view .flw-native video,
body#page-course-view-topics.path-course-view .flw-native .watch-video {
    display:block!important;
    width:100%!important;
    max-width:100%!important;
    height:auto!important;
    aspect-ratio:16/9!important;
    object-fit:contain!important;
    box-sizing:border-box!important;
}
body#page-course-view-topics.path-course-view .flw-native .watch-video {
    margin:0 auto!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card .watch-video,
body#page-course-view-topics.path-course-view .flw-native details.lesson > .lesson-body.video-card .watch-video,
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card .watch-video video,
body#page-course-view-topics.path-course-view .flw-native details.lesson > .lesson-body.video-card .watch-video video {
    aspect-ratio:auto!important;
    height:auto!important;
    min-height:0!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card .watch-video {
    background:transparent!important;
}
body#page-course-view-topics.path-course-view .flw-native section.lesson > .lesson-body.video-card .watch-video video,
body#page-course-view-topics.path-course-view .flw-native details.lesson > .lesson-body.video-card .watch-video video {
    display:block!important;
    object-fit:fill!important;
    vertical-align:top!important;
}
body#page-course-view-topics.path-course-view .flw-native .mediaplugin,
body#page-course-view-topics.path-course-view .flw-native .mediaplugin_videojs,
body#page-course-view-topics.path-course-view .flw-native .mediaplugin_videojs > div,
body#page-course-view-topics.path-course-view .flw-native .video-js,
body#page-course-view-topics.path-course-view .flw-native .video-js.vjs-fluid {
    display:block!important;
    width:100%!important;
    max-width:100%!important;
    min-width:0!important;
    box-sizing:border-box!important;
}
body#page-course-view-topics.path-course-view .flw-native .video-js,
body#page-course-view-topics.path-course-view .flw-native .video-js.vjs-fluid {
    aspect-ratio:16/9!important;
    height:auto!important;
}
body#page-course-view-topics.path-course-view .flw-native .video-js .vjs-tech,
body#page-course-view-topics.path-course-view .flw-native .video-js video {
    width:100%!important;
    height:100%!important;
    object-fit:contain!important;
}
@media(max-width:860px) {
    body#page-course-view-topics.path-course-view .flw-native .hero {
        grid-template-columns:1fr;
    }
}
.flw-native section.lesson.lesson-fold {
    background:var(--card,#fff)!important;
    border:2px solid var(--line,#e7d5ac)!important;
    border-radius:22px!important;
    margin:18px 0!important;
    padding:0!important;
    overflow:hidden!important;
    box-shadow:var(--shadow,0 10px 24px rgba(72,45,10,.12))!important;
}
.activity.label.modtype_label.flw-native-label,
.activity.label.modtype_label.flw-native-label .activity-item,
.activity.label.modtype_label.flw-native-label .description,
.activity.label.modtype_label.flw-native-label .contentwithoutlink,
.activity.label.modtype_label.flw-native-label .no-overflow {
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
    border-radius:22px!important;
}
.activity.label.modtype_label.flw-native-label .activity-item {
    overflow:visible!important;
    width:100%!important;
    padding:0!important;
}
body#page-course-view-topics.path-course-view .activity.label.modtype_label.flw-native-teacher-guide-label,
body#page-course-view-topics.path-course-view .activity.label.modtype_label:has(.flw-native.teacher-guide),
body#page-course-view-topics.path-course-view .activity.label.modtype_label.flw-native-teacher-guide-label .activity-item,
body#page-course-view-topics.path-course-view .activity.label.modtype_label:has(.flw-native.teacher-guide) .activity-item,
body#page-course-view-topics.path-course-view .activity.label.modtype_label.flw-native-teacher-guide-label .description,
body#page-course-view-topics.path-course-view .activity.label.modtype_label:has(.flw-native.teacher-guide) .description,
body#page-course-view-topics.path-course-view .activity.label.modtype_label.flw-native-teacher-guide-label .contentwithoutlink,
body#page-course-view-topics.path-course-view .activity.label.modtype_label:has(.flw-native.teacher-guide) .contentwithoutlink,
body#page-course-view-topics.path-course-view .activity.label.modtype_label.flw-native-teacher-guide-label .no-overflow,
body#page-course-view-topics.path-course-view .activity.label.modtype_label:has(.flw-native.teacher-guide) .no-overflow {
    background:#fff!important;
}
body#page-course-view-topics.path-course-view .course-section.flw-native-teacher-guide-section,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native.teacher-guide),
body#page-course-view-topics.path-course-view .course-section.flw-native-teacher-guide-section .section-item,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native.teacher-guide) .section-item,
body#page-course-view-topics.path-course-view .course-section.flw-native-teacher-guide-section .content,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native.teacher-guide) .content,
body#page-course-view-topics.path-course-view .course-section.flw-native-teacher-guide-section .summary,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native.teacher-guide) .summary {
    background:#fff!important;
}
body#page-course-view-topics.path-course-view .course-section.flw-native-course-section,
body#page-course-view-topics.path-course-view .course-section.flw-native-course-section .section-item,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native),
body#page-course-view-topics.path-course-view .course-section:has(.flw-native) .section-item {
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
}
body#page-course-view-topics.path-course-view .course-section.flw-native-course-section .content,
body#page-course-view-topics.path-course-view .course-section.flw-native-course-section .summary,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native) .content,
body#page-course-view-topics.path-course-view .course-section:has(.flw-native) .summary {
    background:transparent!important;
    border:0!important;
    box-shadow:none!important;
    padding-top:0!important;
    padding-bottom:0!important;
}
.flw-native {
    width:100%;
}
.flw-native > section.card,
.flw-native > article.card,
.flw-native > details.card {
    background:var(--card,#fff)!important;
    border:2px solid var(--line,#e7d5ac)!important;
    border-radius:22px!important;
    box-shadow:0 5px 16px rgba(0,0,0,.06)!important;
    padding:18px!important;
    margin:16px 0!important;
    overflow:visible!important;
}
.flw-native section.lesson.lesson-fold > summary {
    cursor:pointer!important;
    list-style:none!important;
    background:linear-gradient(90deg,#fff,#fff2cc)!important;
    color:var(--ink,#243048)!important;
    margin:0!important;
    padding:18px 22px!important;
    width:100%;
    border-radius:20px 20px 0 0;
    font-size:1.35rem;
    font-weight:900;
    display:flex!important;
    gap:14px!important;
    justify-content:space-between!important;
    align-items:center!important;
}
.flw-native section.lesson.lesson-fold > summary::-webkit-details-marker,
.flw-native section.lesson > summary::-webkit-details-marker {
    display:none;
}
.flw-native .zoom {
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    text-align:center!important;
    line-height:1!important;
    padding:0!important;
}
.flw-native .zoom,
.flw-native .zoom-btn,
.flw-native button.zoom-btn {
    width:38px!important;
    height:38px!important;
    min-width:38px!important;
    min-height:38px!important;
    max-width:38px!important;
    max-height:38px!important;
    padding:0!important;
    border-radius:999px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    line-height:1!important;
    aspect-ratio:1/1!important;
    box-sizing:border-box!important;
}
.flw-native section.lesson,
.flw-native section.lesson .lesson-body {
    width:100%;
}
.flw-native video,
.flw-native .watch-video {
    display:block!important;
    width:100%!important;
    max-width:900px!important;
    height:auto!important;
    margin:0 auto 16px!important;
    border-radius:20px!important;
    background:#000!important;
    object-fit:contain!important;
    accent-color:initial!important;
}
.flw-native video::-webkit-media-controls,
.flw-native video::-webkit-media-controls-panel,
.flw-native video::-webkit-media-controls-timeline,
.flw-native video::-webkit-media-controls-play-button,
.flw-native video::-webkit-media-controls-current-time-display,
.flw-native video::-webkit-media-controls-time-remaining-display,
.flw-native video::-webkit-media-controls-mute-button,
.flw-native video::-webkit-media-controls-volume-slider,
.flw-native video::-webkit-media-controls-fullscreen-button {
    font:revert!important;
    color:revert!important;
    background:revert!important;
    border:revert!important;
    box-shadow:revert!important;
    padding:revert!important;
    margin:revert!important;
    width:revert!important;
    height:revert!important;
    min-width:revert!important;
    min-height:revert!important;
    border-radius:revert!important;
}
.flw-native.teacher-guide h1 a,
.flw-native.teacher-guide h2 a {
    color:inherit!important;
    text-decoration:none!important;
}
.flw-native.image-modal,
.flw-native .image-modal,
.flw-native .modal.open {
    position:fixed!important;
    inset:0!important;
    z-index:2147483000!important;
    background:rgba(0,0,0,.82)!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    padding:22px!important;
    box-sizing:border-box!important;
}
.flw-native.image-modal[aria-hidden="true"]:not(.open),
.flw-native .image-modal[aria-hidden="true"]:not(.open) {
    display:none;
}
.flw-native.image-modal .modal-inner,
.flw-native .modal-inner,
.flw-native .modal-box {
    max-width:min(96vw,1300px);
    max-height:94vh;
    background:#fff;
    border-radius:20px;
    padding:12px;
    position:relative;
    z-index:2147483001!important;
}
.flw-native.image-modal .modal-inner img,
.flw-native .modal-inner img,
.flw-native .modal-box img,
.flw-native #modal-img,
.flw-native #lightbox-img {
    max-width:100%;
    max-height:82vh;
    display:block;
    border-radius:12px;
    object-fit:contain;
}
.flw-native.image-modal .modal-close,
.flw-native .modal-close,
.flw-native .close-modal {
    position:fixed!important;
    right:18px!important;
    top:12px!important;
    z-index:2147483002!important;
    width:46px!important;
    height:46px!important;
    min-width:46px!important;
    min-height:46px!important;
    padding:0!important;
    border:2px solid #fff!important;
    background:var(--red,#b42318)!important;
    color:#fff!important;
    border-radius:999px!important;
    font-size:30px!important;
    line-height:1!important;
    display:grid!important;
    place-items:center!important;
    font-weight:900!important;
    cursor:pointer!important;
    box-shadow:0 6px 18px rgba(0,0,0,.32)!important;
    text-decoration:none!important;
}
.flw-native.image-modal .modal-close > span,
.flw-native .modal-close > span,
.flw-native .close-modal > span {
    display:block!important;
    position:relative!important;
    width:20px!important;
    height:20px!important;
    line-height:0!important;
    text-align:initial!important;
    transform:none!important;
}
.flw-native.image-modal .modal-close .close-line,
.flw-native .modal-close .close-line,
.flw-native .close-modal .close-line {
    position:absolute!important;
    left:50%!important;
    top:50%!important;
    width:22px!important;
    height:3px!important;
    background:#fff!important;
    border-radius:999px!important;
    transform-origin:center!important;
}
.flw-native.image-modal .modal-close .close-line-a,
.flw-native .modal-close .close-line-a,
.flw-native .close-modal .close-line-a {
    transform:translate(-50%,-50%) rotate(45deg)!important;
}
.flw-native.image-modal .modal-close .close-line-b,
.flw-native .modal-close .close-line-b,
.flw-native .close-modal .close-line-b {
    transform:translate(-50%,-50%) rotate(-45deg)!important;
}
@media(max-width:900px) {
    .flw-native section.lesson.lesson-fold > summary {
        align-items:flex-start;
        flex-direction:column;
    }
}
CSS;
}

function rewrite_fragment_for_native(string $fragment, string $webcontenturl, string $rootid = '', string $unitdir = ''): string {
    $fragment = preg_replace('/<a\b[^>]*class="[^"]*\bteacher-link\b[^"]*"[\s\S]*?<\/a>\s*/i', '', $fragment);
    $fragment = preg_replace('/<summary class="lesson-title lesson-summary">[\s\S]*?<\/summary>/i', '', $fragment);
    $fragment = preg_replace('/<details\b([^>]*)>/i', '<section$1>', $fragment);
    $fragment = str_replace('</details>', '</section>', $fragment);
    $fragment = preg_replace('/<section\b([^>]*)\sopen(?:="open"|"")?/i', '<section$1', $fragment);
    $fragment = preg_replace('/\sopen="open"/i', '', $fragment);
    $fragment = preg_replace('/\sopen=""/i', '', $fragment);
    $fragment = str_replace('src="assets/', 'src="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace('poster="assets/', 'poster="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace('href="assets/', 'href="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace('data-img="assets/', 'data-img="' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace("data-img='assets/", "data-img='" . $webcontenturl . "/assets/", $fragment);
    $fragment = str_replace('openImageModal("assets/', 'openImageModal("' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace("openImageModal('assets/", "openImageModal('" . $webcontenturl . "/assets/", $fragment);
    $fragment = str_replace('openImage("assets/', 'openImage("' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace("openImage('assets/", "openImage('" . $webcontenturl . "/assets/", $fragment);
    $fragment = str_replace('openModal("assets/', 'openModal("' . $webcontenturl . '/assets/', $fragment);
    $fragment = str_replace("openModal('assets/", "openModal('" . $webcontenturl . "/assets/", $fragment);
    $fragment = str_replace('src="../', 'src="' . $webcontenturl . '/../', $fragment);
    if ($rootid === '' || $unitdir === '') {
        return '<div class="flw-native">' . $fragment . '</div>';
    }
    $safeid = preg_replace('/[^A-Za-z0-9_-]/', '', $rootid);
    $css = native_section_css($safeid, $unitdir);
    return '<div id="' . htmlspecialchars($safeid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="flw-native" data-flw-section-root="1"><style>' . PHP_EOL . $css . PHP_EOL . '</style>' . $fragment . native_section_script($webcontenturl) . '</div>';
}

function native_section_record(int $section, string $name, string $fragment, string $webcontenturl, string $unitdir): array {
    $rootid = native_section_root_id($section, $name);
    return ['section' => $section, 'name' => $name, 'html' => rewrite_fragment_for_native($fragment, $webcontenturl, $rootid, $unitdir)];
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
    $html = str_replace('openImage("assets/', 'openImage("' . $webcontenturl . '/assets/', $html);
    $html = str_replace("openImage('assets/", "openImage('" . $webcontenturl . "/assets/", $html);
    $html = str_replace('openModal("assets/', 'openModal("' . $webcontenturl . '/assets/', $html);
    $html = str_replace("openModal('assets/", "openModal('" . $webcontenturl . "/assets/", $html);
    $html = str_replace('url(assets/', 'url(' . $webcontenturl . '/assets/', $html);
    $html = str_replace('url("assets/', 'url("' . $webcontenturl . '/assets/', $html);
    $html = str_replace("url('assets/", "url('" . $webcontenturl . "/assets/", $html);
    $html = preg_replace_callback('/<h1\b([^>]*)>([\s\S]*?)<\/h1>/i', function($matches) {
        $attrs = $matches[1];
        $title = trim($matches[2]);
        if ($title === '') {
            return $matches[0];
        }
        $spanstyle = 'color:inherit!important;text-decoration:none!important;';
        return '<h1' . $attrs . '><span class="nolink" style="' . $spanstyle . '">' . $title . '</span></h1>';
    }, $html, 1);
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

function first_unit_cover_image(string $unitdir): string {
    $root = realpath($unitdir);
    if (!$root) {
        return '';
    }
    $candidates = [];
    $index = $unitdir . '/index.html';
    if (is_file($index)) {
        $html = file_get_contents($index);
        if ($html !== false && preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $matches)) {
            $src = preg_replace('/[?#].*$/', '', str_replace('\\', '/', trim($matches[1])));
            if ($src !== '' && !preg_match('/^(?:[a-z]+:)?\/\//i', $src) && strpos($src, '/') !== 0) {
                $candidates[] = $unitdir . '/' . $src;
            }
        }
    }
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
        foreach (glob($unitdir . '/assets/images/*.' . $ext) ?: [] as $path) {
            $candidates[] = $path;
        }
    }
    foreach (['PNG', 'JPG', 'JPEG', 'WEBP', 'GIF'] as $ext) {
        foreach (glob($unitdir . '/assets/images/*.' . $ext) ?: [] as $path) {
            $candidates[] = $path;
        }
    }
    $candidates = array_values(array_unique($candidates));
    natsort($candidates);
    foreach ($candidates as $candidate) {
        $path = realpath($candidate);
        if ($path && is_file($path) && strpos($path, $root . DIRECTORY_SEPARATOR) === 0) {
            return $path;
        }
    }
    return '';
}

function matching_close_tag_position(string $html, int $openpos, string $tag): int {
    $openend = strpos($html, '>', $openpos);
    if ($openend === false) {
        return -1;
    }
    $needle = preg_quote($tag, '/');
    $depth = 1;
    $offset = $openend + 1;
    while (preg_match('/<\/?' . $needle . '\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $tagtext = $match[0][0];
        $pos = $match[0][1];
        if (preg_match('/^<\//', $tagtext)) {
            $depth--;
            if ($depth === 0) {
                return $pos;
            }
        } else if (!preg_match('/\/\s*>$/', $tagtext)) {
            $depth++;
        }
        $offset = $pos + strlen($tagtext);
    }
    return -1;
}

function extract_main_inner(string $html): string {
    if (!preg_match('/<main\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
        return $html;
    }
    $open = $match[0][1];
    $openend = $open + strlen($match[0][0]);
    $close = matching_close_tag_position($html, $open, 'main');
    if ($close < 0 || $close <= $openend) {
        return $html;
    }
    return substr($html, $openend, $close - $openend);
}

function top_level_unit_blocks(string $html): array {
    $blocks = [];
    if (preg_match('/<main\b[^>]*>/i', $html, $mainmatch, PREG_OFFSET_CAPTURE)) {
        $prefix = substr($html, 0, $mainmatch[0][1]);
        $offset = 0;
        while (preg_match('/<(header|nav|section|details|article)\b[^>]*>/i', $prefix, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $tag = strtolower($match[1][0]);
            $open = $match[0][1];
            $close = matching_close_tag_position($prefix, $open, $tag);
            if ($close < 0) {
                break;
            }
            $closeend = strpos($prefix, '>', $close);
            if ($closeend === false) {
                break;
            }
            $fragment = substr($prefix, $open, $closeend - $open + 1);
            if (trim(strip_tags($fragment)) !== '') {
                $blocks[] = $fragment;
            }
            $offset = $closeend + 1;
        }
    }
    $content = extract_main_inner($html);
    $offset = 0;
    while (preg_match('/<(header|nav|section|details|article)\b[^>]*>/i', $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $tag = strtolower($match[1][0]);
        $open = $match[0][1];
        $close = matching_close_tag_position($content, $open, $tag);
        if ($close < 0) {
            break;
        }
        $closeend = strpos($content, '>', $close);
        if ($closeend === false) {
            break;
        }
        $fragment = substr($content, $open, $closeend - $open + 1);
        if (trim(strip_tags($fragment)) !== '') {
            $blocks[] = $fragment;
        }
        $offset = $closeend + 1;
    }
    return $blocks;
}

function fragment_has_class(string $fragment, string $class): bool {
    if (!preg_match('/^<\w+\b[^>]*\bclass=["\']([^"\']*)["\']/i', trim($fragment), $match)) {
        return false;
    }
    $classes = preg_split('/\s+/', trim($match[1]));
    return in_array($class, $classes, true);
}

function fragment_tag_name(string $fragment): string {
    return preg_match('/^<(\w+)\b/i', trim($fragment), $match) ? strtolower($match[1]) : '';
}

function fragment_id(string $fragment): string {
    return preg_match('/^<\w+\b[^>]*\bid=["\']([^"\']+)["\']/i', trim($fragment), $match) ? strtolower($match[1]) : '';
}

function clean_heading_text(string $html): string {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function summary_title_from_lesson(string $fragment, int $lessonnumber): string {
    if (!preg_match('/<summary[^>]*>([\s\S]*?)<\/summary>/i', $fragment, $summarymatch)) {
        return 'Lesson ' . $lessonnumber;
    }
    $summaryhtml = preg_replace('/<small\b[\s\S]*?<\/small>/i', ' ', $summarymatch[1]);
    $summaryhtml = preg_replace('/<span\b[^>]*\bclass=["\'][^"\']*\b(?:plus|fold-symbol)\b[^"\']*["\'][^>]*>[\s\S]*?<\/span>/i', ' ', $summaryhtml);
    $summaryhtml = preg_replace('/<span\b[^>]*>\s*\d+\s*<\/span>/i', ' ', $summaryhtml, 1);
    $summary = clean_heading_text($summaryhtml);
    if ($summary === '') {
        return 'Lesson ' . $lessonnumber;
    }
    $summary = preg_replace('/^[\s\-\x{2212}–—]+/u', '', $summary);
    $title = preg_replace('/^Lesson\s+' . $lessonnumber . '\s*[:—\-\x{2212}–]\s*/u', 'Lesson ' . $lessonnumber . ' - ', $summary);
    if (stripos($title, 'Lesson ' . $lessonnumber) !== 0) {
        $title = 'Lesson ' . $lessonnumber . ' - ' . $title;
    }
    return $title;
}

function name_top_level_unit_block(string $fragment, int &$lessonnumber): string {
    $id = fragment_id($fragment);
    $tag = fragment_tag_name($fragment);
    $summary = '';
    if ($tag === 'details' && preg_match('/<summary[^>]*>([\s\S]*?)<\/summary>/i', $fragment, $summarymatch)) {
        $summaryhtml = preg_replace('/<span\b[^>]*\bclass=["\'][^"\']*\b(?:plus|fold-symbol)\b[^"\']*["\'][^>]*>[\s\S]*?<\/span>/i', ' ', $summarymatch[1]);
        $summary = clean_heading_text($summaryhtml);
    }
    if (preg_match('/^<header\b/i', trim($fragment)) || fragment_has_class($fragment, 'hero') || $id === 'top') {
        return 'Unit Overview';
    }
    if ($tag === 'nav' && fragment_has_class($fragment, 'path')) {
        return 'Unit Map';
    }
    if (fragment_has_class($fragment, 'unit-brief')) {
        return 'Unit Brief';
    }
    if (fragment_has_class($fragment, 'lesson-select')) {
        return 'Lesson Map';
    }
    if (fragment_has_class($fragment, 'core-language')) {
        return 'Core Language';
    }
    if ($id === 'opener' || preg_match('/^Unit Opener\b/i', $summary)) {
        return 'Unit Opener';
    }
    if ($tag === 'details' && preg_match('/^Watch\b/i', $summary)) {
        return 'Watch Lesson';
    }
    if ($tag === 'details' && preg_match('/^Final Project\b/i', $summary)) {
        return 'Final Project';
    }
    if ($tag === 'details' || fragment_has_class($fragment, 'lesson') || fragment_has_class($fragment, 'lesson-fold')) {
        $name = summary_title_from_lesson($fragment, $lessonnumber);
        $lessonnumber++;
        return $name;
    }
    if ($id === 'watch' || stripos($fragment, 'watch') !== false && preg_match('/\bwatch\b/i', substr($fragment, 0, 300))) {
        return 'Watch Lesson';
    }
    if (fragment_has_class($fragment, 'project-card') && preg_match('/<h([1-3])[^>]*>([\s\S]*?)<\/h\1>/i', $fragment, $projectheading)) {
        $title = clean_heading_text($projectheading[2]);
        if ($title !== '') {
            return $title;
        }
    }
    if ($id === 'project' || stripos($fragment, 'project') !== false && preg_match('/\bproject\b/i', substr($fragment, 0, 300))) {
        return 'Final Project';
    }
    if (fragment_has_class($fragment, 'progress-panel') || $id === 'progress') {
        return 'Progress';
    }
    if (preg_match('/<h([1-3])[^>]*>([\s\S]*?)<\/h\1>/i', $fragment, $heading)) {
        $title = clean_heading_text($heading[2]);
        if ($title !== '') {
            return $title;
        }
    }
    return 'Section';
}

function set_course_overview_image(stdClass $course, string $unitdir): string {
    $image = first_unit_cover_image($unitdir);
    if ($image === '') {
        return '';
    }
    $context = context_course::instance($course->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
    $filename = clean_param(basename($image), PARAM_FILE);
    if ($filename === '') {
        $filename = 'course-cover.' . pathinfo($image, PATHINFO_EXTENSION);
    }
    $record = [
        'contextid' => $context->id,
        'component' => 'course',
        'filearea' => 'overviewfiles',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $fs->create_file_from_pathname($record, $image);
    return $filename;
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
            native_section_record(1, 'Overview & Unit Map', $overview, $webcontenturl, dirname($sourcefile)),
        ];
        if ($story !== '') {
            $sectionnumber = count($sections) + 1;
            $sections[] = native_section_record($sectionnumber, 'Unit Story', $story, $webcontenturl, dirname($sourcefile));
        }
        for ($i = 1; $i <= 7; $i++) {
            $fragment = extract_required_fragment($html, '/<details class="lesson lesson-fold" id="lesson-' . $i . '"[^>]*>[\s\S]*?<\/details>/i', 'lesson ' . $i);
            $fragment = add_lesson_bullet_overrides($fragment, $i);
            $title = 'Lesson ' . $i;
            if (preg_match('/<summary[^>]*>[\s\S]*?<h2>(.*?)<\/h2>/i', $fragment, $titlematch)) {
                $title .= ' - ' . trim(strip_tags($titlematch[1]));
            }
            $sectionnumber = count($sections) + 1;
            $sections[] = native_section_record($sectionnumber, $title, $fragment, $webcontenturl, dirname($sourcefile));
        }
        $watch = extract_required_fragment($html, '/<section class="watch-box" id="watch">[\s\S]*?(?=<section class="project-box" id="project">)/i', 'watch');
        $project = extract_required_fragment($html, '/<section class="project-box" id="project">[\s\S]*?<section class="portfolio-box" id="portfolio">[\s\S]*?<\/section>\s*(?=<\/main>)/i', 'project');
        $sectionnumber = count($sections) + 1;
        $sections[] = native_section_record($sectionnumber, 'Watch Lesson', $watch, $webcontenturl, dirname($sourcefile));
        $sectionnumber = count($sections) + 1;
        $sections[] = native_section_record($sectionnumber, 'Final Project', $project, $webcontenturl, dirname($sourcefile));
        return $sections;
    }

    $html = preg_replace('/<nav\b[\s\S]*?<\/nav>\s*/i', '', $html, 1);
    $blocks = top_level_unit_blocks($html);
    if (!$blocks) {
        fail_now('Could not extract top-level unit blocks from prototype.');
    }
    $sections = [];
    $lessonnumber = 1;
    $usednames = [];
    foreach ($blocks as $fragment) {
        $name = name_top_level_unit_block($fragment, $lessonnumber);
        $basename = $name;
        $dedupe = 2;
        while (isset($usednames[strtolower($name)])) {
            $name = $basename . ' ' . $dedupe;
            $dedupe++;
        }
        $usednames[strtolower($name)] = true;
        $sectionnumber = count($sections) + 1;
        $sections[] = native_section_record($sectionnumber, $name, $fragment, $webcontenturl, dirname($sourcefile));
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
    $preferredid = null;
    $preferredcategoryid = 0;
    if ($old = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING)) {
        $oldid = (int)$old->id;
        if ($oldid >= 101 && $oldid < 10000) {
            $preferredid = $oldid;
        }
        $preferredcategoryid = (int)$old->category;
        delete_course($old, false);
        fix_course_sortorder();
    }
    $used = $DB->get_fieldset_select('course', 'id', 'id >= 101', []);
    $used = array_map('intval', $used);
    $targetid = null;
    if ($preferredid && !in_array($preferredid, $used, true)) {
        $targetid = $preferredid;
    }
    if (!$targetid) {
        for ($candidate = 101; $candidate < 10000; $candidate++) {
            if (!in_array($candidate, $used, true)) {
                $targetid = $candidate;
                break;
            }
        }
    }
    if (!$targetid) {
        fail_now('No unused production course id is available.');
    }

    $categoryid = 0;
    if ($preferredcategoryid && $DB->record_exists('course_categories', ['id' => $preferredcategoryid])) {
        $categoryid = $preferredcategoryid;
    }
    if (!$categoryid) {
        $categoryid = (int)$targetcategoryid;
    }
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
file_put_contents($webcontentdir . '/flw_native_test.css', preg_replace('/^<style>\s*|\s*<\/style>$/', '', native_css()) . PHP_EOL . discover_source_css($unitdir) . PHP_EOL . native_conversion_compat_css());
file_put_contents($webcontentdir . '/flw_native_test.js', preg_replace('/^<script>\s*|\s*<\/script>$/', '', native_script($webcontenturl)));
$nativecontent = build_native_sections($unitdir . '/index.html', $webcontenturl);
$lastcontentsection = 0;
foreach ($nativecontent as $content) {
    $lastcontentsection = max($lastcontentsection, (int)$content['section']);
}
$quizsection = $lastcontentsection + 1;
$teachersection = $quizsection + 1;

$course = create_test_course_below_100($shortname, $fullname, $coursesummary);
$coursecover = set_course_overview_image($course, $unitdir);
course_create_sections_if_missing($course, range(0, $teachersection));
remove_sections_above($course, $teachersection);

$createdlabels = [];
foreach ($nativecontent as $content) {
    update_section_name($course, (int)$content['section'], $content['name']);
    $createdlabels[] = [
        'section' => (int)$content['section'],
        'name' => $content['name'],
        'cmid' => add_label_resource($course, (int)$content['section'], $content['name'], $content['html']),
    ];
}

update_section_name($course, $quizsection, 'Quiz');
$quizdata = add_quiz_activity($course, $quizsection, 'Unit 10 Family Tree', '');
$quizimport = $questioncount > 0 ? import_quiz_xml_to_quiz($course, $quizdata['quiz'], $quizxml) : ['questionids' => []];

update_section_name($course, $teachersection, 'Teacher Guide');
$teacherhtml = rewrite_teacher_guide_for_native($unitdir . '/teacher_guide.html', $webcontenturl);
$teachercmid = add_label_resource($course, $teachersection, 'Teacher Guide', $teacherhtml);
set_coursemodule_visible($teachercmid, 0);
$courseindexhidden = $createdlabels;
$courseindexhidden[] = ['cmid' => (int)$quizdata['cmid']];
$courseindexhidden[] = ['cmid' => (int)$teachercmid];
$indexassetcmid = !empty($createdlabels[0]['cmid']) ? (int)$createdlabels[0]['cmid'] : 0;
if ($indexassetcmid) {
    $indexcm = get_coursemodule_from_id('label', $indexassetcmid, 0, false, IGNORE_MISSING);
    if ($indexcm && ($indexlabel = $DB->get_record('label', ['id' => $indexcm->instance], '*', IGNORE_MISSING))) {
        $indexlabel->intro .= '<style>' . course_index_label_css($courseindexhidden) . '</style><script>' . course_index_label_script($courseindexhidden) . '</script>';
        $DB->update_record('label', $indexlabel);
    }
}
if ($section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $teachersection])) {
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
    'course_cover_image' => $coursecover,
    'label_resources' => $createdlabels,
    'quiz_cmid' => $quizdata['cmid'],
    'quiz_question_count' => count($quizimport['questionids']),
    'teacher_guide_cmid' => $teachercmid,
    'enrolments' => $enrolments,
    'courseurl' => $CFG->wwwroot . '/course/view.php?id=' . (int)$course->id,
    'quizurl' => $CFG->wwwroot . '/mod/quiz/view.php?id=' . $quizdata['cmid'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
