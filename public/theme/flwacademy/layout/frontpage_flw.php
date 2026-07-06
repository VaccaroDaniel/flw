<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');

$extraclasses = ['flw-start-page'];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = theme_flwacademy_prepare_primary_navigation($primary->export_for_template($renderer));
$flwlangorder = [
    'en' => 0,
    'ru' => 1,
    'zh_cn' => 2,
    'zh' => 2,
    'ja' => 3,
    'de' => 4,
    'fr' => 5,
    'es' => 6,
];
$flwgetlangcode = static function(array $item): string {
    $link = $item['link'] ?? [];
    $url = (string)($link['url'] ?? '');
    $text = (string)($link['text'] ?? '');

    if (preg_match('/[?&]lang=([a-z_]+)/i', $url, $matches)) {
        return strtolower($matches[1]);
    }
    if (preg_match('/\(([a-z_]+)\)/i', $text, $matches)) {
        return strtolower($matches[1]);
    }
    return '';
};
if (!empty($primarymenu['lang']['items']) && is_array($primarymenu['lang']['items'])) {
    usort($primarymenu['lang']['items'], static function(array $left, array $right) use ($flwlangorder, $flwgetlangcode): int {
        $leftcode = $flwgetlangcode($left);
        $rightcode = $flwgetlangcode($right);
        $leftorder = $flwlangorder[$leftcode] ?? 99;
        $rightorder = $flwlangorder[$rightcode] ?? 99;
        if ($leftorder === $rightorder) {
            return strnatcasecmp((string)($left['link']['text'] ?? ''), (string)($right['link']['text'] ?? ''));
        }
        return $leftorder <=> $rightorder;
    });
}
ob_start();
echo $OUTPUT->main_content();
$maincontent = ob_get_clean();

$loginurl = new moodle_url('/login/index.php');
$dashboardurl = new moodle_url('/my/');
$courseindexurl = new moodle_url('/course/index.php');
$frontpageurl = new moodle_url('/');

$frontpagecopy = [
    'en' => [
        'navlearningpath' => 'Learning Path',
        'navcourses' => 'Courses',
        'navdaily' => 'Daily Language',
        'navnews' => "Today's News",
        'navtips' => 'Tips',
        'navfaq' => 'FAQ',
        'brand' => 'Foreign Language World',
        'herotitle' => 'Start with your level. Follow a visible language path.',
        'herolead' => 'FLW connects placement, CEFR-style goals, knowledge points, daily practice, and teacher support inside one Moodle-ready learning journey.',
        'metricsaria' => 'FLW theme signals',
        'metriclanguages' => 'languages',
        'metricplacement' => 'placement items per skill',
        'metricmottos' => 'daily mottos',
        'startplacement' => 'Start placement',
        'browsecoursemap' => 'Browse course map',
        'enterdashboard' => 'Enter dashboard',
        'accesskicker' => 'Moodle-ready entry',
        'accesstitle' => 'One front door for learners, teachers, and schools.',
        'learnerstitle' => 'Learners',
        'learnersdesc' => 'Placement, path, practice, progress',
        'teacherstitle' => 'Teachers',
        'teachersdesc' => 'Books, homework, feedback, evidence',
        'schoolstitle' => 'Schools',
        'schoolsdesc' => 'Courses, cohorts, exams, reports',
        'loginflw' => 'Log in to FLW',
        'previewdaily' => 'Preview daily language',
        'patharia' => 'FLW learning path preview',
        'route1title' => 'Diagnose level',
        'route1text' => 'Placement finds CEFR or language-framework evidence.',
        'route2title' => 'Open the next KP',
        'route2text' => 'Course maps point learners to the exact knowledge point.',
        'route3title' => 'Show progress',
        'route3text' => 'Practice and teacher evidence feed the learning path.',
        'categorieskicker' => 'Language categories',
        'categoriestitle' => 'Choose the language world you want to enter.',
        'viewalllanguages' => 'View all languages',
        'languagealt' => 'course category',
        'dailykicker' => 'Daily language',
        'dailytitle' => 'A small language habit before every lesson.',
        'dailywordlabel' => "Today's Word",
        'dailyword' => 'path',
        'dailywordtext' => 'A route through lessons, knowledge points, practice, and progress evidence.',
        'dailysentencelabel' => "Today's Sentence",
        'dailysentence' => 'I can see my next learning step.',
        'dailysentencetext' => 'Daily sentences can later come from FLW multilingual motto and vocabulary data.',
        'todaylabel' => 'Today',
        'todaytitle' => 'Placement -> KP -> Progress',
        'todaytext' => 'The front page points every learner toward the next useful action.',
        'newskicker' => "Today's News",
        'newstitle' => 'Multilingual news for language noticing.',
        'news1meta' => 'World English | Easy',
        'news1title' => 'Students compare greetings across six languages.',
        'news1text' => 'A short multilingual news article for noticing polite openings, names, countries, and classroom phrases.',
        'news2meta' => 'Chinese | HSK 2',
        'news2title' => 'A city library opens a language exchange evening.',
        'news2text' => 'Learners read the same event in simple Chinese and English, then collect useful community words.',
        'news3meta' => 'French | B1',
        'news3title' => 'Young travelers use local radio to plan a weekend.',
        'news3text' => 'The article links news reading with listening keywords, time expressions, and practical travel vocabulary.',
        'tipskicker' => 'Tips',
        'tipstitle' => 'Small learning habits that make practice easier.',
        'tip1meta' => 'Speaking',
        'tip1title' => 'Use one safe sentence before a free conversation.',
        'tip1text' => 'Prepare a reusable sentence frame, say it aloud, then change one detail for a real answer.',
        'tip2meta' => 'Listening',
        'tip2title' => 'Listen once for the situation, once for details.',
        'tip2text' => 'Identify who speaks, where they are, and why they are speaking before chasing every word.',
        'tip3meta' => 'Vocabulary',
        'tip3title' => 'Save words with a sentence, not alone.',
        'tip3text' => 'A word becomes useful faster when it is tied to a person, place, emotion, or action.',
        'userskicker' => 'Top users',
        'userstitle' => 'Learners active in Foreign Language World.',
        'listeningstreak' => 'Listening streak',
        'speakingroom' => 'Speaking room',
        'readingnotes' => 'Reading notes',
        'exampractice' => 'Exam practice',
        'dailysentencebrief' => 'Daily sentence',
        'faqkicker' => 'FAQ',
        'faqtitle' => 'Common questions before joining FLW.',
        'faq1q' => 'Do I need to log in to read articles?',
        'faq1a' => "No. Today's News, Tips, and daily language boxes are available before login.",
        'faq2q' => 'What happens after login?',
        'faq2a' => 'Learners enter the FLW dashboard with self study, practice, exams, collaboration, and teacher support.',
        'faq3q' => 'Can teachers use FLW?',
        'faq3a' => 'Yes. The protected dashboard includes teacher tools for books, homework, feedback, and learner evidence.',
        'faq4q' => "Are Today's Word and Today's Sentence for exams only?",
        'faq4a' => 'No. They are educational language inputs for broad learning, communication, and vocabulary growth.',
    ],
    'ru' => [
        'navlearningpath' => 'Путь обучения', 'navcourses' => 'Курсы', 'navdaily' => 'Ежедневный язык', 'navnews' => 'Новости сегодня', 'navtips' => 'Советы', 'navfaq' => 'FAQ',
        'herotitle' => 'Начните со своего уровня. Следуйте видимому языковому пути.', 'herolead' => 'FLW соединяет уровень, цели CEFR, знания, ежедневную практику и поддержку учителя в одном пути Moodle.',
        'metriclanguages' => 'языков', 'metricplacement' => 'заданий на навык', 'metricmottos' => 'ежедневных девизов',
        'startplacement' => 'Начать тест', 'browsecoursemap' => 'Карта курса', 'enterdashboard' => 'Войти в панель',
        'accesskicker' => 'Вход Moodle', 'accesstitle' => 'Один вход для учащихся, учителей и школ.',
        'learnerstitle' => 'Учащиеся', 'learnersdesc' => 'Уровень, путь, практика, прогресс', 'teacherstitle' => 'Учителя', 'teachersdesc' => 'Книги, задания, отзыв, доказательства', 'schoolstitle' => 'Школы', 'schoolsdesc' => 'Курсы, группы, экзамены, отчеты',
        'loginflw' => 'Войти в FLW', 'previewdaily' => 'Посмотреть ежедневный язык',
        'route1title' => 'Определите уровень', 'route1text' => 'Тест находит доказательства уровня и языковой рамки.', 'route2title' => 'Откройте следующий KP', 'route2text' => 'Карта курса показывает точную точку знания.', 'route3title' => 'Покажите прогресс', 'route3text' => 'Практика и данные учителя питают путь обучения.',
        'categorieskicker' => 'Языковые категории', 'categoriestitle' => 'Выберите языковой мир, в который хотите войти.', 'viewalllanguages' => 'Все языки', 'languagealt' => 'категория курса',
        'dailykicker' => 'Ежедневный язык', 'dailytitle' => 'Маленькая языковая привычка перед каждым уроком.', 'dailywordlabel' => 'Слово дня', 'dailyword' => 'путь', 'dailywordtext' => 'Маршрут через уроки, знания, практику и прогресс.', 'dailysentencelabel' => 'Предложение дня', 'dailysentence' => 'Я вижу свой следующий шаг.', 'dailysentencetext' => 'Ежедневные предложения позже будут приходить из многоязычных данных FLW.', 'todaylabel' => 'Сегодня', 'todaytitle' => 'Уровень -> KP -> Прогресс', 'todaytext' => 'Главная страница ведет учащегося к следующему полезному действию.',
        'newskicker' => 'Новости сегодня', 'newstitle' => 'Многоязычные новости для языкового наблюдения.',
        'tipskicker' => 'Советы', 'tipstitle' => 'Маленькие привычки, которые облегчают практику.',
        'userskicker' => 'Лучшие пользователи', 'userstitle' => 'Активные учащиеся в Foreign Language World.', 'listeningstreak' => 'Серия аудирования', 'speakingroom' => 'Комната говорения', 'readingnotes' => 'Заметки чтения', 'exampractice' => 'Экзамены', 'dailysentencebrief' => 'Фраза дня',
        'faqkicker' => 'FAQ', 'faqtitle' => 'Частые вопросы перед входом в FLW.',
    ],
    'zh_cn' => [
        'navlearningpath' => '学习路径', 'navcourses' => '课程', 'navdaily' => '每日语言', 'navnews' => '今日新闻', 'navtips' => '技巧', 'navfaq' => 'FAQ',
        'herotitle' => '从你的水平开始，沿着清晰的语言路径前进。', 'herolead' => 'FLW 在 Moodle 中连接水平测试、CEFR 目标、知识点、每日练习和教师支持。',
        'metriclanguages' => '种语言', 'metricplacement' => '每项技能测试题', 'metricmottos' => '每日格言',
        'startplacement' => '开始测试', 'browsecoursemap' => '浏览课程地图', 'enterdashboard' => '进入面板',
        'accesskicker' => 'Moodle 入口', 'accesstitle' => '学习者、教师和学校的统一入口。',
        'learnerstitle' => '学习者', 'learnersdesc' => '测试、路径、练习、进度', 'teacherstitle' => '教师', 'teachersdesc' => '教材、作业、反馈、证据', 'schoolstitle' => '学校', 'schoolsdesc' => '课程、班级、考试、报告',
        'loginflw' => '登录 FLW', 'previewdaily' => '预览每日语言',
        'route1title' => '诊断水平', 'route1text' => '测试发现 CEFR 或语言框架证据。', 'route2title' => '打开下一个 KP', 'route2text' => '课程地图指向准确的知识点。', 'route3title' => '显示进度', 'route3text' => '练习和教师证据更新学习路径。',
        'categorieskicker' => '语言分类', 'categoriestitle' => '选择你想进入的语言世界。', 'viewalllanguages' => '查看全部语言', 'languagealt' => '课程分类',
        'dailykicker' => '每日语言', 'dailytitle' => '每节课前的小语言习惯。', 'dailywordlabel' => '今日词汇', 'dailyword' => '路径', 'dailywordtext' => '通过课程、知识点、练习和进度证据的路线。', 'dailysentencelabel' => '今日句子', 'dailysentence' => '我能看到下一步学习。', 'dailysentencetext' => '每日句子以后可来自 FLW 多语言格言和词汇数据。', 'todaylabel' => '今天', 'todaytitle' => '测试 -> KP -> 进度', 'todaytext' => '首页把每位学习者带到下一个有用行动。',
        'newskicker' => '今日新闻', 'newstitle' => '用于语言观察的多语言新闻。',
        'tipskicker' => '技巧', 'tipstitle' => '让练习更容易的小习惯。',
        'userskicker' => '优秀用户', 'userstitle' => 'Foreign Language World 中活跃的学习者。', 'listeningstreak' => '听力连续练习', 'speakingroom' => '口语房间', 'readingnotes' => '阅读笔记', 'exampractice' => '考试练习', 'dailysentencebrief' => '每日句子',
        'faqkicker' => 'FAQ', 'faqtitle' => '加入 FLW 前的常见问题。',
    ],
    'de' => [
        'navlearningpath' => 'Lernpfad', 'navcourses' => 'Kurse', 'navdaily' => 'Tägliche Sprache', 'navnews' => 'Nachrichten', 'navtips' => 'Tipps', 'navfaq' => 'FAQ',
        'herotitle' => 'Beginne mit deinem Niveau. Folge einem sichtbaren Sprachpfad.', 'herolead' => 'FLW verbindet Einstufung, CEFR-Ziele, Wissenspunkte, tägliche Übung und Lehrkräfteunterstützung in Moodle.',
        'metriclanguages' => 'Sprachen', 'metricplacement' => 'Einstufungsaufgaben je Fertigkeit', 'metricmottos' => 'Tagesmottos',
        'startplacement' => 'Einstufung starten', 'browsecoursemap' => 'Kurskarte ansehen', 'enterdashboard' => 'Dashboard öffnen',
        'accesskicker' => 'Moodle-Einstieg', 'accesstitle' => 'Ein Eingang für Lernende, Lehrkräfte und Schulen.',
        'learnerstitle' => 'Lernende', 'learnersdesc' => 'Einstufung, Pfad, Übung, Fortschritt', 'teacherstitle' => 'Lehrkräfte', 'teachersdesc' => 'Bücher, Aufgaben, Feedback, Nachweise', 'schoolstitle' => 'Schulen', 'schoolsdesc' => 'Kurse, Kohorten, Prüfungen, Berichte',
        'loginflw' => 'Bei FLW anmelden', 'previewdaily' => 'Tägliche Sprache ansehen',
        'route1title' => 'Niveau diagnostizieren', 'route1text' => 'Die Einstufung findet CEFR- oder Sprachrahmen-Nachweise.', 'route2title' => 'Nächsten KP öffnen', 'route2text' => 'Kurskarten zeigen den genauen Wissenspunkt.', 'route3title' => 'Fortschritt zeigen', 'route3text' => 'Übung und Lehrernachweise speisen den Lernpfad.',
        'categorieskicker' => 'Sprachkategorien', 'categoriestitle' => 'Wähle die Sprachwelt, die du betreten möchtest.', 'viewalllanguages' => 'Alle Sprachen ansehen', 'languagealt' => 'Kurskategorie',
        'dailykicker' => 'Tägliche Sprache', 'dailytitle' => 'Eine kleine Sprachgewohnheit vor jeder Lektion.',
        'newskicker' => 'Nachrichten', 'newstitle' => 'Mehrsprachige Nachrichten zum Sprachbeobachten.',
        'tipskicker' => 'Tipps', 'tipstitle' => 'Kleine Lerngewohnheiten, die Übung leichter machen.',
        'userskicker' => 'Top-Nutzer', 'userstitle' => 'Aktive Lernende in Foreign Language World.', 'faqkicker' => 'FAQ', 'faqtitle' => 'Häufige Fragen vor dem Start mit FLW.',
    ],
    'ja' => [
        'navlearningpath' => '学習パス', 'navcourses' => 'コース', 'navdaily' => '今日の言語', 'navnews' => '今日のニュース', 'navtips' => 'ヒント', 'navfaq' => 'FAQ',
        'herotitle' => '自分のレベルから始め、見える言語パスを進みましょう。', 'herolead' => 'FLW は Moodle の中で、レベル診断、CEFR 目標、知識ポイント、毎日の練習、教師支援をつなぎます。',
        'metriclanguages' => '言語', 'metricplacement' => '技能別診断項目', 'metricmottos' => '毎日のモットー',
        'startplacement' => '診断を始める', 'browsecoursemap' => 'コースマップを見る', 'enterdashboard' => 'ダッシュボードへ',
        'accesskicker' => 'Moodle 入口', 'accesstitle' => '学習者、教師、学校のための一つの入口。',
        'learnerstitle' => '学習者', 'learnersdesc' => '診断、パス、練習、進捗', 'teacherstitle' => '教師', 'teachersdesc' => '教材、宿題、フィードバック、証拠', 'schoolstitle' => '学校', 'schoolsdesc' => 'コース、コホート、試験、レポート',
        'loginflw' => 'FLW にログイン', 'previewdaily' => '今日の言語を見る',
        'route1title' => 'レベルを診断', 'route1text' => '診断で CEFR や言語フレームの証拠を見つけます。', 'route2title' => '次の KP を開く', 'route2text' => 'コースマップが正確な知識ポイントへ導きます。', 'route3title' => '進捗を表示', 'route3text' => '練習と教師の証拠が学習パスに反映されます。',
        'categorieskicker' => '言語カテゴリ', 'categoriestitle' => '入りたい言語世界を選びましょう。', 'viewalllanguages' => 'すべての言語を見る', 'languagealt' => 'コースカテゴリ',
        'dailykicker' => '今日の言語', 'dailytitle' => '毎回の授業前の小さな言語習慣。',
        'newskicker' => '今日のニュース', 'newstitle' => '言語に気づくための多言語ニュース。',
        'tipskicker' => 'ヒント', 'tipstitle' => '練習を楽にする小さな学習習慣。',
        'userskicker' => 'トップユーザー', 'userstitle' => 'Foreign Language World で活動中の学習者。', 'faqkicker' => 'FAQ', 'faqtitle' => 'FLW 参加前のよくある質問。',
    ],
    'es' => [
        'navlearningpath' => 'Ruta', 'navcourses' => 'Cursos', 'navdaily' => 'Idioma diario', 'navnews' => 'Noticias', 'navtips' => 'Consejos', 'navfaq' => 'FAQ',
        'herotitle' => 'Empieza con tu nivel. Sigue una ruta visible.', 'herolead' => 'FLW conecta nivelación, objetivos CEFR, puntos de conocimiento, práctica diaria y apoyo docente en Moodle.',
        'metriclanguages' => 'idiomas', 'metricplacement' => 'ítems por habilidad', 'metricmottos' => 'lemas diarios',
        'startplacement' => 'Empezar nivelación', 'browsecoursemap' => 'Ver mapa del curso', 'enterdashboard' => 'Entrar al panel',
        'accesskicker' => 'Entrada Moodle', 'accesstitle' => 'Una puerta para estudiantes, docentes y escuelas.',
        'learnerstitle' => 'Estudiantes', 'learnersdesc' => 'Nivel, ruta, práctica, progreso', 'teacherstitle' => 'Docentes', 'teachersdesc' => 'Libros, tareas, comentarios, evidencia', 'schoolstitle' => 'Escuelas', 'schoolsdesc' => 'Cursos, cohortes, exámenes, informes',
        'loginflw' => 'Entrar en FLW', 'previewdaily' => 'Ver idioma diario',
        'route1title' => 'Diagnosticar nivel', 'route1text' => 'La nivelación encuentra evidencia CEFR o del marco lingüístico.', 'route2title' => 'Abrir el siguiente KP', 'route2text' => 'Los mapas del curso apuntan al punto exacto.', 'route3title' => 'Mostrar progreso', 'route3text' => 'La práctica y la evidencia docente alimentan la ruta.',
        'categorieskicker' => 'Categorías de idioma', 'categoriestitle' => 'Elige el mundo lingüístico que quieres entrar.', 'viewalllanguages' => 'Ver todos los idiomas', 'languagealt' => 'categoría del curso',
        'dailykicker' => 'Idioma diario', 'dailytitle' => 'Un pequeño hábito antes de cada lección.',
        'newskicker' => 'Noticias', 'newstitle' => 'Noticias multilingües para notar el idioma.',
        'tipskicker' => 'Consejos', 'tipstitle' => 'Pequeños hábitos que facilitan la práctica.',
        'userskicker' => 'Usuarios destacados', 'userstitle' => 'Estudiantes activos en Foreign Language World.', 'faqkicker' => 'FAQ', 'faqtitle' => 'Preguntas comunes antes de unirse a FLW.',
    ],
    'fr' => [
        'navlearningpath' => 'Parcours', 'navcourses' => 'Cours', 'navdaily' => 'Langue du jour', 'navnews' => 'Actualités', 'navtips' => 'Conseils', 'navfaq' => 'FAQ',
        'herotitle' => 'Commence par ton niveau. Suis un parcours visible.', 'herolead' => 'FLW relie positionnement, objectifs CECRL, points de connaissance, pratique quotidienne et soutien enseignant dans Moodle.',
        'metriclanguages' => 'langues', 'metricplacement' => 'items par compétence', 'metricmottos' => 'mottos du jour',
        'startplacement' => 'Commencer le test', 'browsecoursemap' => 'Voir la carte du cours', 'enterdashboard' => 'Entrer au tableau de bord',
        'accesskicker' => 'Entrée Moodle', 'accesstitle' => 'Une porte pour apprenants, enseignants et écoles.',
        'learnerstitle' => 'Apprenants', 'learnersdesc' => 'Positionnement, parcours, pratique, progrès', 'teacherstitle' => 'Enseignants', 'teachersdesc' => 'Livres, devoirs, feedback, preuves', 'schoolstitle' => 'Écoles', 'schoolsdesc' => 'Cours, cohortes, examens, rapports',
        'loginflw' => 'Se connecter à FLW', 'previewdaily' => 'Voir la langue du jour',
        'route1title' => 'Diagnostiquer le niveau', 'route1text' => 'Le positionnement trouve des preuves CECRL ou du cadre de langue.', 'route2title' => 'Ouvrir le KP suivant', 'route2text' => 'Les cartes de cours indiquent le point précis.', 'route3title' => 'Afficher le progrès', 'route3text' => 'La pratique et les preuves enseignant alimentent le parcours.',
        'categorieskicker' => 'Catégories de langue', 'categoriestitle' => 'Choisis le monde linguistique où entrer.', 'viewalllanguages' => 'Voir toutes les langues', 'languagealt' => 'catégorie de cours',
        'dailykicker' => 'Langue du jour', 'dailytitle' => 'Une petite habitude avant chaque leçon.',
        'newskicker' => 'Actualités', 'newstitle' => 'Actualités multilingues pour observer la langue.',
        'tipskicker' => 'Conseils', 'tipstitle' => 'Petites habitudes qui rendent la pratique plus facile.',
        'userskicker' => 'Meilleurs utilisateurs', 'userstitle' => 'Apprenants actifs dans Foreign Language World.', 'faqkicker' => 'FAQ', 'faqtitle' => 'Questions fréquentes avant de rejoindre FLW.',
    ],
];

$frontpagecopy['ru'] += [
    'news1meta' => 'Мировой английский | Легко', 'news1title' => 'Учащиеся сравнивают приветствия на шести языках.', 'news1text' => 'Короткая многоязычная новость помогает замечать вежливые начала, имена, страны и классные фразы.',
    'news2meta' => 'Китайский | HSK 2', 'news2title' => 'Городская библиотека открывает вечер языкового обмена.', 'news2text' => 'Учащиеся читают одно событие на простом китайском и английском и собирают полезные слова.',
    'news3meta' => 'Французский | B1', 'news3title' => 'Молодые путешественники используют местное радио для планов на выходные.', 'news3text' => 'Статья связывает чтение новостей с ключевыми словами аудирования, временем и лексикой путешествий.',
    'tip1meta' => 'Говорение', 'tip1title' => 'Используйте одно безопасное предложение перед свободным разговором.', 'tip1text' => 'Подготовьте фразу, произнесите ее вслух и измените одну деталь для настоящего ответа.',
    'tip2meta' => 'Аудирование', 'tip2title' => 'Слушайте один раз ситуацию, второй раз детали.', 'tip2text' => 'Сначала определите кто говорит, где и зачем, прежде чем ловить каждое слово.',
    'tip3meta' => 'Словарь', 'tip3title' => 'Сохраняйте слова с предложением, не отдельно.', 'tip3text' => 'Слово быстрее становится полезным, когда связано с человеком, местом, эмоцией или действием.',
    'faq1q' => 'Нужно ли входить, чтобы читать статьи?', 'faq1a' => 'Нет. Новости, советы и ежедневные языковые блоки доступны до входа.',
    'faq2q' => 'Что происходит после входа?', 'faq2a' => 'Учащиеся входят в панель FLW с самостоятельным обучением, практикой, экзаменами, сотрудничеством и поддержкой учителя.',
    'faq3q' => 'Могут ли учителя использовать FLW?', 'faq3a' => 'Да. Защищенная панель содержит инструменты учителя для книг, заданий, отзывов и доказательств.',
    'faq4q' => 'Слово дня и предложение дня только для экзаменов?', 'faq4a' => 'Нет. Это учебный языковой ввод для общения, словаря и общего развития.',
];

$frontpagecopy['zh_cn'] += [
    'news1meta' => '世界英语 | 简单', 'news1title' => '学生比较六种语言的问候。', 'news1text' => '一篇简短的多语言新闻，用来观察礼貌开场、姓名、国家和课堂表达。',
    'news2meta' => '中文 | HSK 2', 'news2title' => '城市图书馆开设语言交流晚会。', 'news2text' => '学习者用简单中文和英语阅读同一事件，并收集有用的社区词汇。',
    'news3meta' => '法语 | B1', 'news3title' => '年轻旅行者用本地广播计划周末。', 'news3text' => '文章把新闻阅读和听力关键词、时间表达、旅行词汇连接起来。',
    'tip1meta' => '口语', 'tip1title' => '自由对话前先用一个安全句。', 'tip1text' => '准备一个可重复使用的句型，大声说出来，再改变一个细节。',
    'tip2meta' => '听力', 'tip2title' => '第一次听场景，第二次听细节。', 'tip2text' => '先判断谁在说、在哪里、为什么说，再追每一个词。',
    'tip3meta' => '词汇', 'tip3title' => '保存单词时带上句子。', 'tip3text' => '词语和人物、地点、情感或动作连接时，会更快变得有用。',
    'faq1q' => '阅读文章需要登录吗？', 'faq1a' => '不需要。今日新闻、技巧和每日语言框在登录前可见。',
    'faq2q' => '登录后会发生什么？', 'faq2a' => '学习者进入 FLW 面板，可进行自学、练习、考试、协作和教师支持。',
    'faq3q' => '教师可以使用 FLW 吗？', 'faq3a' => '可以。受保护的面板包含教材、作业、反馈和学习证据工具。',
    'faq4q' => '今日词汇和今日句子只为考试吗？', 'faq4a' => '不是。它们是用于交流、词汇和综合学习的教育语言输入。',
];

$frontpagecopy['de'] += [
    'dailywordlabel' => 'Wort des Tages', 'dailyword' => 'Pfad', 'dailywordtext' => 'Ein Weg durch Lektionen, Wissenspunkte, Übung und Fortschrittsnachweise.', 'dailysentencelabel' => 'Satz des Tages', 'dailysentence' => 'Ich sehe meinen nächsten Lernschritt.', 'dailysentencetext' => 'Tägliche Sätze können später aus mehrsprachigen FLW-Motto- und Wortschatzdaten kommen.', 'todaylabel' => 'Heute', 'todaytitle' => 'Einstufung -> KP -> Fortschritt', 'todaytext' => 'Die Startseite führt Lernende zur nächsten sinnvollen Handlung.',
    'news1meta' => 'World English | Leicht', 'news1title' => 'Lernende vergleichen Begrüßungen in sechs Sprachen.', 'news1text' => 'Eine kurze mehrsprachige Nachricht zum Erkennen höflicher Anfänge, Namen, Länder und Klassenphrasen.', 'news2meta' => 'Chinesisch | HSK 2', 'news2title' => 'Eine Stadtbibliothek eröffnet einen Sprachtauschabend.', 'news2text' => 'Lernende lesen dasselbe Ereignis in einfachem Chinesisch und Englisch und sammeln nützliche Wörter.', 'news3meta' => 'Französisch | B1', 'news3title' => 'Junge Reisende nutzen Lokalradio für das Wochenende.', 'news3text' => 'Der Artikel verbindet Nachrichtenlesen mit Hörschlüsselwörtern, Zeitangaben und Reisevokabular.',
    'tip1meta' => 'Sprechen', 'tip1title' => 'Nutze einen sicheren Satz vor freiem Gespräch.', 'tip1text' => 'Bereite einen Satzrahmen vor, sprich ihn laut und ändere ein Detail für eine echte Antwort.', 'tip2meta' => 'Hören', 'tip2title' => 'Höre einmal für die Situation, einmal für Details.', 'tip2text' => 'Erkenne zuerst wer spricht, wo sie sind und warum sie sprechen.', 'tip3meta' => 'Wortschatz', 'tip3title' => 'Speichere Wörter mit einem Satz.', 'tip3text' => 'Ein Wort wird schneller nützlich, wenn es mit Person, Ort, Gefühl oder Handlung verbunden ist.',
    'listeningstreak' => 'Hör-Serie', 'speakingroom' => 'Sprechraum', 'readingnotes' => 'Lesenotizen', 'exampractice' => 'Prüfungstraining', 'dailysentencebrief' => 'Tagessatz',
    'faq1q' => 'Muss ich mich anmelden, um Artikel zu lesen?', 'faq1a' => 'Nein. Nachrichten, Tipps und tägliche Sprachfelder sind vor der Anmeldung sichtbar.', 'faq2q' => 'Was passiert nach der Anmeldung?', 'faq2a' => 'Lernende öffnen das FLW-Dashboard mit Selbststudium, Übung, Prüfungen, Zusammenarbeit und Lehrerunterstützung.', 'faq3q' => 'Können Lehrkräfte FLW nutzen?', 'faq3a' => 'Ja. Das geschützte Dashboard enthält Werkzeuge für Bücher, Aufgaben, Feedback und Nachweise.', 'faq4q' => 'Sind Wort und Satz des Tages nur für Prüfungen?', 'faq4a' => 'Nein. Sie sind Lerninput für Kommunikation, Wortschatz und allgemeines Lernen.',
];

$frontpagecopy['ja'] += [
    'dailywordlabel' => '今日の単語', 'dailyword' => 'パス', 'dailywordtext' => 'レッスン、知識ポイント、練習、進捗証拠を通る道です。', 'dailysentencelabel' => '今日の文', 'dailysentence' => '次の学習ステップが見えます。', 'dailysentencetext' => '今日の文は後で FLW の多言語モットーや語彙データから出せます。', 'todaylabel' => '今日', 'todaytitle' => '診断 -> KP -> 進捗', 'todaytext' => 'フロントページは学習者を次の有用な行動へ導きます。',
    'news1meta' => 'World English | やさしい', 'news1title' => '学習者が六つの言語のあいさつを比べます。', 'news1text' => '丁寧な始まり、名前、国、教室表現に気づくための短い多言語ニュースです。', 'news2meta' => '中国語 | HSK 2', 'news2title' => '市立図書館が言語交流の夜を開きます。', 'news2text' => '同じ出来事を簡単な中国語と英語で読み、役立つ地域語彙を集めます。', 'news3meta' => 'フランス語 | B1', 'news3title' => '若い旅行者が地元ラジオで週末を計画します。', 'news3text' => 'ニュース読解を聞き取りキーワード、時間表現、旅行語彙につなげます。',
    'tip1meta' => 'スピーキング', 'tip1title' => '自由会話の前に安全な一文を使う。', 'tip1text' => '使い回せる文型を準備し、声に出してから一つだけ内容を変えます。', 'tip2meta' => 'リスニング', 'tip2title' => '一回目は状況、二回目は詳細を聞く。', 'tip2text' => 'すべての語を追う前に、誰がどこで何のために話すかを確認します。', 'tip3meta' => '語彙', 'tip3title' => '単語だけでなく文と一緒に保存する。', 'tip3text' => '人、場所、感情、動作と結びつくと単語は使いやすくなります。',
    'listeningstreak' => 'リスニング継続', 'speakingroom' => 'スピーキングルーム', 'readingnotes' => '読解メモ', 'exampractice' => '試験練習', 'dailysentencebrief' => '今日の文',
    'faq1q' => '記事を読むにはログインが必要ですか？', 'faq1a' => 'いいえ。ニュース、ヒント、今日の言語はログイン前に見られます。', 'faq2q' => 'ログイン後はどうなりますか？', 'faq2a' => '学習者は自学、練習、試験、協働、教師支援を含む FLW ダッシュボードに入ります。', 'faq3q' => '教師も FLW を使えますか？', 'faq3a' => 'はい。保護されたダッシュボードに教材、宿題、フィードバック、学習証拠のツールがあります。', 'faq4q' => '今日の単語と文は試験だけのためですか？', 'faq4a' => 'いいえ。コミュニケーション、語彙、広い学習のための教育的な言語入力です。',
];

$frontpagecopy['es'] += [
    'dailywordlabel' => 'Palabra de hoy', 'dailyword' => 'ruta', 'dailywordtext' => 'Un camino por lecciones, puntos de conocimiento, práctica y evidencia de progreso.', 'dailysentencelabel' => 'Frase de hoy', 'dailysentence' => 'Puedo ver mi siguiente paso de aprendizaje.', 'dailysentencetext' => 'Las frases diarias podrán venir de datos multilingües de lemas y vocabulario FLW.', 'todaylabel' => 'Hoy', 'todaytitle' => 'Nivelación -> KP -> Progreso', 'todaytext' => 'La portada guía a cada estudiante hacia la próxima acción útil.',
    'news1meta' => 'World English | Fácil', 'news1title' => 'Estudiantes comparan saludos en seis idiomas.', 'news1text' => 'Una noticia multilingüe breve para notar aperturas corteses, nombres, países y frases de clase.', 'news2meta' => 'Chino | HSK 2', 'news2title' => 'Una biblioteca abre una noche de intercambio lingüístico.', 'news2text' => 'Los estudiantes leen el mismo evento en chino simple e inglés y reúnen palabras útiles.', 'news3meta' => 'Francés | B1', 'news3title' => 'Jóvenes viajeros usan la radio local para planear el fin de semana.', 'news3text' => 'El artículo conecta lectura de noticias con palabras clave de escucha, tiempo y vocabulario de viaje.',
    'tip1meta' => 'Hablar', 'tip1title' => 'Usa una frase segura antes de conversar libremente.', 'tip1text' => 'Prepara una estructura, dila en voz alta y cambia un detalle para una respuesta real.', 'tip2meta' => 'Escuchar', 'tip2title' => 'Escucha una vez la situación y otra los detalles.', 'tip2text' => 'Identifica quién habla, dónde está y por qué habla antes de perseguir cada palabra.', 'tip3meta' => 'Vocabulario', 'tip3title' => 'Guarda palabras con una frase.', 'tip3text' => 'Una palabra sirve antes cuando se une a una persona, lugar, emoción o acción.',
    'listeningstreak' => 'Racha de escucha', 'speakingroom' => 'Sala oral', 'readingnotes' => 'Notas de lectura', 'exampractice' => 'Práctica de examen', 'dailysentencebrief' => 'Frase diaria',
    'faq1q' => '¿Necesito iniciar sesión para leer artículos?', 'faq1a' => 'No. Noticias, consejos y bloques de idioma diario están disponibles antes de iniciar sesión.', 'faq2q' => '¿Qué pasa después de iniciar sesión?', 'faq2a' => 'Los estudiantes entran al panel FLW con autoestudio, práctica, exámenes, colaboración y apoyo docente.', 'faq3q' => '¿Los docentes pueden usar FLW?', 'faq3a' => 'Sí. El panel protegido incluye herramientas para libros, tareas, comentarios y evidencia.', 'faq4q' => '¿La palabra y frase de hoy son solo para exámenes?', 'faq4a' => 'No. Son entrada lingüística educativa para comunicación, vocabulario y aprendizaje amplio.',
];

$frontpagecopy['fr'] += [
    'dailywordlabel' => 'Mot du jour', 'dailyword' => 'parcours', 'dailywordtext' => 'Un chemin à travers leçons, points de connaissance, pratique et preuves de progrès.', 'dailysentencelabel' => 'Phrase du jour', 'dailysentence' => 'Je vois ma prochaine étape d’apprentissage.', 'dailysentencetext' => 'Les phrases du jour pourront venir des données multilingues FLW.', 'todaylabel' => 'Aujourd’hui', 'todaytitle' => 'Positionnement -> KP -> Progrès', 'todaytext' => 'La page d’accueil guide chaque apprenant vers la prochaine action utile.',
    'news1meta' => 'World English | Facile', 'news1title' => 'Des apprenants comparent les salutations dans six langues.', 'news1text' => 'Une courte actualité multilingue pour remarquer ouvertures polies, noms, pays et phrases de classe.', 'news2meta' => 'Chinois | HSK 2', 'news2title' => 'Une bibliothèque ouvre une soirée d’échange linguistique.', 'news2text' => 'Les apprenants lisent le même événement en chinois simple et en anglais, puis collectent des mots utiles.', 'news3meta' => 'Français | B1', 'news3title' => 'De jeunes voyageurs utilisent la radio locale pour planifier le week-end.', 'news3text' => 'L’article relie lecture d’actualité, mots-clés d’écoute, temps et vocabulaire de voyage.',
    'tip1meta' => 'Expression orale', 'tip1title' => 'Utilise une phrase sûre avant une conversation libre.', 'tip1text' => 'Prépare une structure, dis-la à voix haute, puis change un détail pour une vraie réponse.', 'tip2meta' => 'Écoute', 'tip2title' => 'Écoute une fois la situation, puis les détails.', 'tip2text' => 'Repère qui parle, où et pourquoi avant de suivre chaque mot.', 'tip3meta' => 'Vocabulaire', 'tip3title' => 'Garde les mots avec une phrase.', 'tip3text' => 'Un mot devient utile plus vite quand il est lié à une personne, un lieu, une émotion ou une action.',
    'listeningstreak' => 'Série d’écoute', 'speakingroom' => 'Salle orale', 'readingnotes' => 'Notes de lecture', 'exampractice' => 'Entraînement examen', 'dailysentencebrief' => 'Phrase du jour',
    'faq1q' => 'Faut-il se connecter pour lire les articles ?', 'faq1a' => 'Non. Les actualités, conseils et blocs de langue du jour sont disponibles avant connexion.', 'faq2q' => 'Que se passe-t-il après la connexion ?', 'faq2a' => 'Les apprenants entrent dans le tableau FLW avec autoformation, pratique, examens, collaboration et soutien enseignant.', 'faq3q' => 'Les enseignants peuvent-ils utiliser FLW ?', 'faq3a' => 'Oui. Le tableau protégé contient des outils pour livres, devoirs, feedback et preuves.', 'faq4q' => 'Le mot et la phrase du jour sont-ils seulement pour les examens ?', 'faq4a' => 'Non. Ce sont des entrées linguistiques pour communication, vocabulaire et apprentissage général.',
];

$langcode = current_language();
if ($langcode === 'zh' || strpos($langcode, 'zh_') === 0) {
    $langcode = 'zh_cn';
}
$copy = array_merge($frontpagecopy['en'], $frontpagecopy[$langcode] ?? $frontpagecopy[substr($langcode, 0, 2)] ?? []);

$flwlangcodes = ['en', 'ru', 'zh_cn', 'ja', 'de', 'fr', 'es'];
$flwlangitems = [];
$stringmanager = get_string_manager();
$currentlangcode = current_language();
if ($currentlangcode === 'zh' || strpos($currentlangcode, 'zh_') === 0) {
    $currentlangcode = 'zh_cn';
}
foreach ($flwlangcodes as $code) {
    if ($code !== 'en' && !is_dir($CFG->dataroot . '/lang/' . $code)) {
        continue;
    }

    $text = $stringmanager->get_string('thislanguage', 'langconfig', null, $code) . ' ‎(' . $code . ')‎';
    $active = $code === $currentlangcode;
    $langurl = new moodle_url($PAGE->url);
    $langurl->param('lang', $code);
    $flwlangitems[] = [
        'link' => [
            'title' => $text,
            'text' => $text,
            'url' => $active ? '#' : $langurl->out(false),
            'isactive' => $active,
            'attributes' => [
                ['key' => 'lang', 'value' => str_replace('_', '-', $code)],
            ],
        ],
    ];
}
$flwlangtitle = $stringmanager->get_string('thislanguage', 'langconfig', null, $currentlangcode) .
    ' ‎(' . $currentlangcode . ')‎';
$flwlangmenu = [
    'title' => $flwlangtitle,
    'items' => $flwlangitems,
];

$languages = [
    [
        'name' => 'English',
        'native' => 'English',
        'framework' => 'CEFR A1-C2',
        'motto' => 'Know your level. Follow your path. See your progress.',
        'accent' => 'teal',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-english', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
    [
        'name' => 'Russian',
        'native' => 'Русский',
        'framework' => 'TORFL A1-C2',
        'motto' => 'Учись шаг за шагом, говори увереннее.',
        'accent' => 'coral',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-russian', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
    [
        'name' => 'Chinese',
        'native' => '中文',
        'framework' => 'HSK 1-6',
        'motto' => '一步一步，听说读写都进步。',
        'accent' => 'yellow',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-chinese', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
    [
        'name' => 'Japanese',
        'native' => '日本語',
        'framework' => 'JLPT N5-N1',
        'motto' => '文字から会話へ、毎日少しずつ。',
        'accent' => 'blue',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-japanese', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
    [
        'name' => 'German',
        'native' => 'Deutsch',
        'framework' => 'CEFR A1-C2',
        'motto' => 'Lerne klar, übe gezielt, wachse sicher.',
        'accent' => 'green',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-german', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
    [
        'name' => 'French',
        'native' => 'Français',
        'framework' => 'CEFR A1-C2',
        'motto' => 'Comprends mieux, parle plus naturellement.',
        'accent' => 'teal',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-french', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
    [
        'name' => 'Spanish',
        'native' => 'Español',
        'framework' => 'CEFR A1-C2',
        'motto' => 'Aprende con ritmo, practica con propósito.',
        'accent' => 'coral',
        'imageurl' => $OUTPUT->image_url('frontpage/lang-spanish', 'theme_flwacademy')->out(false),
        'url' => $courseindexurl->out(false),
    ],
];

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, [
        'context' => context_course::instance(SITEID),
        'escape' => false,
    ]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $flwlangmenu,
    'heroimageurl' => $OUTPUT->image_url('frontpage/login-main', 'theme_flwacademy')->out(false),
    'loginurl' => $loginurl->out(false),
    'dashboardurl' => $dashboardurl->out(false),
    'courseindexurl' => $courseindexurl->out(false),
    'frontpageurl' => $frontpageurl->out(false),
    'maincontent' => $maincontent,
    'languages' => $languages,
    'copy' => $copy,
];

echo $OUTPUT->render_from_template('theme_flwacademy/frontpage', $templatecontext);
