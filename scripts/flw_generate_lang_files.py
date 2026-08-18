import ast
import json
import re
import time
import urllib.parse
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]

COMPONENTS = [
    (
        ROOT / "public/theme/flwacademy/lang/en/theme_flwacademy.php",
        ROOT / "public/theme/flwacademy/lang",
        "theme_flwacademy.php",
    ),
    (
        ROOT / "public/local/flwexam/lang/en/local_flwexam.php",
        ROOT / "public/local/flwexam/lang",
        "local_flwexam.php",
    ),
    (
        ROOT / "public/local/flwmedia/lang/en/local_flwmedia.php",
        ROOT / "public/local/flwmedia/lang",
        "local_flwmedia.php",
    ),
    (
        ROOT / "public/local/mldict/lang/en/local_mldict.php",
        ROOT / "public/local/mldict/lang",
        "local_mldict.php",
    ),
    (
        ROOT / "public/blocks/mldict/lang/en/block_mldict.php",
        ROOT / "public/blocks/mldict/lang",
        "block_mldict.php",
    ),
]

LANGUAGES = {
    "de": "de",
    "es": "es",
    "fr": "fr",
    "ja": "ja",
    "ru": "ru",
    "zh_cn": "zh-CN",
}

CACHE_PATH = ROOT / "scripts/.flw_lang_translation_cache.json"

DO_NOT_TRANSLATE_PREFIXES = (
    "privacy:metadata",
)

DO_NOT_TRANSLATE_KEYS = {
    "pluginname",
    "choosereadme",
    "flwexam:viewown",
    "flwexam:viewall",
    "flwexam:submitresult",
    "flwexam:manageexams",
    "flwexam:manageselfexams",
    "flwexam:manageteacherexams",
    "flwexam:manageofficialexams",
    "flwexam:verifycertificate",
    "flwexam:revokecertificate",
    "flwmedia:manage",
    "flwmedia:seedtestdata",
    "flwmedia:view",
    "flwmedia:viewreports",
    "mldict:view",
    "mldict:manage",
}

BRAND_TOKENS = [
    "FLW",
    "Moodle",
    "Quiz",
    "CEFR",
    "K-12",
    "HSK",
    "JLPT",
    "TORFL",
    "AI",
]

EXACT = {
    "de": {
        "Home": "Startseite",
        "Dashboard": "Dashboard",
        "Practice": "Üben",
        "Dictionary": "Wörterbuch",
        "Exam": "Prüfung",
        "Demo": "Demo",
        "Self Study": "Selbststudium",
        "Teacher": "Lehrer",
        "English": "Englisch",
        "Russian": "Russisch",
        "Chinese": "Chinesisch",
        "Japanese": "Japanisch",
        "German": "Deutsch",
        "French": "Französisch",
        "Spanish": "Spanisch",
        "Language": "Sprache",
        "CEFR level": "GER-Niveau",
        "Actions": "Aktionen",
        "Attempts": "Versuche",
        "Ready": "Bereit",
        "My results": "Meine Ergebnisse",
        "Find Self Exams": "Selbstprüfungen suchen",
        "Teacher and Official Exam Sessions": "Lehrer- und offizielle Prüfungstermine",
        "Self Exam Sessions": "Selbstprüfungstermine",
        "Start Self Exam": "Selbstprüfung starten",
        "Start Teacher Exam": "Lehrerprüfung starten",
        "Start Official Exam": "Offizielle Prüfung starten",
        "Learn a language by using it.": "Lernen Sie eine Sprache, indem Sie sie verwenden.",
        "FLW word lab": "FLW Wortlabor",
        "Multilingual dictionary": "Mehrsprachiges Wörterbuch",
        "Ready to start": "Bereit zum Start",
        "Browse courses": "Kurse durchsuchen",
        "No courses yet": "Noch keine Kurse",
        "Open": "Öffnen",
        "Open course": "Kurs öffnen",
        "No activities yet": "Noch keine Aktivitäten",
        "Captions": "Untertitel",
        "{$a} confidence": "{$a} Vertrauen",
        "Day streak": "Tages-Serie",
        "{$a} day streak": "{$a} Tage in Folge",
        "This week": "Diese Woche",
        "Placement": "Einstufung",
        "Not placed": "Nicht eingestuft",
        "Provisional": "Vorläufig",
        "Main path with repair": "Hauptpfad mit Nacharbeit",
        "Main path": "Hauptpfad",
        "Review path": "Wiederholungspfad",
        "Teacher review first": "Zuerst Lehrerprüfung",
        "Listening": "Hören",
        "Speaking": "Sprechen",
        "Reading": "Lesen",
        "Writing": "Schreiben",
        "Grammar": "Grammatik",
        "Pronunciation": "Aussprache",
        "Vocabulary": "Wortschatz",
        "Complete": "Abgeschlossen",
        "In Progress": "In Bearbeitung",
        "Next": "Weiter",
        "Find Unit {$a}": "Einheit {$a} finden",
        "Go to Unit {$a}": "Zu Einheit {$a}",
        "{$a} Listening Sample": "{$a} Hörbeispiel",
        "Listen for the speaker's purpose in a {$a->language} {$a->level} dialogue.": "Achten Sie in einem {$a->language}-Dialog auf Niveau {$a->level} auf die Absicht des Sprechers.",
        "Choose the best summary after listening twice.": "Wählen Sie nach zweimaligem Hören die beste Zusammenfassung.",
        "{$a} Reading Sample": "{$a} Lesebeispiel",
        "Read a short {$a->language} text at {$a->level} and identify the main claim.": "Lesen Sie einen kurzen {$a->language}-Text auf Niveau {$a->level} und erkennen Sie die Hauptaussage.",
        "Select the sentence that best matches the writer's intention.": "Wählen Sie den Satz, der die Absicht des Autors am besten trifft.",
        "{$a} Use of Language": "{$a} Sprachgebrauch",
        "Complete a {$a->level} grammar and vocabulary item for {$a->language}.": "Bearbeiten Sie eine Grammatik- und Wortschatzaufgabe auf Niveau {$a->level} für {$a->language}.",
        "Choose the answer that fits the context and register.": "Wählen Sie die Antwort, die zu Kontext und Register passt.",
        "{$a} Speaking or Writing": "{$a} Sprechen oder Schreiben",
        "Produce a short response for a {$a->language} {$a->level} situation.": "Verfassen Sie eine kurze Antwort für eine {$a->language}-Situation auf Niveau {$a->level}.",
        "Record or draft a response, then compare it with the checklist.": "Nehmen Sie eine Antwort auf oder entwerfen Sie sie und vergleichen Sie sie dann mit der Checkliste.",
        "Recommended from your latest placement profile": "Empfohlen aus Ihrem neuesten Einstufungsprofil",
        "Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.": "Beginnen Sie mit der empfohlenen Einheit und ergänzen Sie vor dem nächsten Checkpoint Aussprache- und Schreibübungen.",
        "Category description": "Kategoriebeschreibung",
        "Primary and secondary school": "Grund- und Sekundarschule",
        "Six-year courses for": "Sechsjährige Kurse für",
        "School Course": "Schulkurs",
        "No primary or secondary subcategories yet.": "Noch keine Unterkategorien für Grund- oder Sekundarschule.",
        "University": "Universität",
        "Two-year courses for": "Zweijährige Kurse für",
        "University Course": "Universitätskurs",
        "No university subcategories yet.": "Noch keine Universitätsunterkategorien.",
        "school courses have been added yet.": "Schulkurse wurden noch nicht hinzugefügt.",
    },
    "es": {
        "Home": "Inicio",
        "Dashboard": "Panel",
        "Practice": "Práctica",
        "Dictionary": "Diccionario",
        "Exam": "Examen",
        "Demo": "Demo",
        "Self Study": "Autoestudio",
        "Teacher": "Profesor",
        "English": "Inglés",
        "Russian": "Ruso",
        "Chinese": "Chino",
        "Japanese": "Japonés",
        "German": "Alemán",
        "French": "Francés",
        "Spanish": "Español",
        "Language": "Idioma",
        "CEFR level": "Nivel MCER",
        "Actions": "Acciones",
        "Attempts": "Intentos",
        "Ready": "Listo",
        "My results": "Mis resultados",
        "Find Self Exams": "Buscar autoexámenes",
        "Teacher and Official Exam Sessions": "Sesiones de examen del profesor y oficiales",
        "Self Exam Sessions": "Sesiones de autoexamen",
        "Start Self Exam": "Iniciar autoexamen",
        "Start Teacher Exam": "Iniciar examen del profesor",
        "Start Official Exam": "Iniciar examen oficial",
        "Learn a language by using it.": "Aprende un idioma usándolo.",
        "FLW word lab": "Laboratorio de palabras FLW",
        "Multilingual dictionary": "Diccionario multilingüe",
        "Ready to start": "Listo para empezar",
        "Browse courses": "Explorar cursos",
        "No courses yet": "Aún no hay cursos",
        "Open": "Abrir",
        "Open course": "Abrir curso",
        "No activities yet": "Aún no hay actividades",
        "Captions": "Subtítulos",
        "{$a} confidence": "{$a} de confianza",
        "Day streak": "Racha de días",
        "{$a} day streak": "Racha de {$a} días",
        "This week": "Esta semana",
        "Placement": "Nivelación",
        "Not placed": "Sin nivelación",
        "Provisional": "Provisional",
        "Main path with repair": "Ruta principal con refuerzo",
        "Main path": "Ruta principal",
        "Review path": "Ruta de repaso",
        "Teacher review first": "Revisión del profesor primero",
        "Listening": "Comprensión auditiva",
        "Speaking": "Expresión oral",
        "Reading": "Lectura",
        "Writing": "Escritura",
        "Grammar": "Gramática",
        "Pronunciation": "Pronunciación",
        "Vocabulary": "Vocabulario",
        "Complete": "Completado",
        "In Progress": "En progreso",
        "Next": "Siguiente",
        "Find Unit {$a}": "Buscar unidad {$a}",
        "Go to Unit {$a}": "Ir a la unidad {$a}",
        "{$a} Listening Sample": "{$a} Muestra de escucha",
        "Listen for the speaker's purpose in a {$a->language} {$a->level} dialogue.": "Escucha el propósito del hablante en un diálogo de {$a->language} {$a->level}.",
        "Choose the best summary after listening twice.": "Elige el mejor resumen después de escuchar dos veces.",
        "{$a} Reading Sample": "{$a} Muestra de lectura",
        "Read a short {$a->language} text at {$a->level} and identify the main claim.": "Lee un texto breve en {$a->language} de nivel {$a->level} e identifica la idea principal.",
        "Select the sentence that best matches the writer's intention.": "Selecciona la oración que mejor coincide con la intención del autor.",
        "{$a} Use of Language": "{$a} Uso del idioma",
        "Complete a {$a->level} grammar and vocabulary item for {$a->language}.": "Completa un ejercicio de gramática y vocabulario de {$a->language} {$a->level}.",
        "Choose the answer that fits the context and register.": "Elige la respuesta que se ajusta al contexto y al registro.",
        "{$a} Speaking or Writing": "{$a} Expresión oral o escritura",
        "Produce a short response for a {$a->language} {$a->level} situation.": "Produce una respuesta breve para una situación de {$a->language} {$a->level}.",
        "Record or draft a response, then compare it with the checklist.": "Graba o redacta una respuesta y compárala con la lista de comprobación.",
        "Recommended from your latest placement profile": "Recomendado desde tu último perfil de nivelación",
        "Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.": "Empieza en la unidad recomendada y añade práctica de pronunciación y escritura antes del próximo punto de control.",
        "Category description": "Descripción de la categoría",
        "Primary and secondary school": "Primaria y secundaria",
        "Six-year courses for": "Cursos de seis años para",
        "School Course": "Curso escolar",
        "No primary or secondary subcategories yet.": "Aún no hay subcategorías de primaria o secundaria.",
        "University": "Universidad",
        "Two-year courses for": "Cursos de dos años para",
        "University Course": "Curso universitario",
        "No university subcategories yet.": "Aún no hay subcategorías universitarias.",
        "school courses have been added yet.": "aún no tiene cursos escolares añadidos.",
    },
    "fr": {
        "Home": "Accueil",
        "Dashboard": "Tableau de bord",
        "Practice": "Pratique",
        "Dictionary": "Dictionnaire",
        "Exam": "Examen",
        "Demo": "Démo",
        "Self Study": "Auto-apprentissage",
        "Teacher": "Enseignant",
        "English": "Anglais",
        "Russian": "Russe",
        "Chinese": "Chinois",
        "Japanese": "Japonais",
        "German": "Allemand",
        "French": "Français",
        "Spanish": "Espagnol",
        "Language": "Langue",
        "CEFR level": "Niveau CECR",
        "Actions": "Actions",
        "Attempts": "Tentatives",
        "Ready": "Prêt",
        "My results": "Mes résultats",
        "Find Self Exams": "Trouver des examens autonomes",
        "Teacher and Official Exam Sessions": "Sessions d’examen enseignant et officielles",
        "Self Exam Sessions": "Sessions d’examen autonome",
        "Start Self Exam": "Commencer l’examen autonome",
        "Start Teacher Exam": "Commencer l’examen enseignant",
        "Start Official Exam": "Commencer l’examen officiel",
        "Learn a language by using it.": "Apprenez une langue en l’utilisant.",
        "FLW word lab": "Laboratoire de mots FLW",
        "Multilingual dictionary": "Dictionnaire multilingue",
        "Ready to start": "Prêt à commencer",
        "Browse courses": "Parcourir les cours",
        "No courses yet": "Aucun cours pour le moment",
        "Open": "Ouvrir",
        "Open course": "Ouvrir le cours",
        "No activities yet": "Aucune activité pour le moment",
        "Captions": "Sous-titres",
        "{$a} confidence": "{$a} de confiance",
        "Day streak": "Série de jours",
        "{$a} day streak": "Série de {$a} jours",
        "This week": "Cette semaine",
        "Placement": "Positionnement",
        "Not placed": "Non positionné",
        "Provisional": "Provisoire",
        "Main path with repair": "Parcours principal avec remédiation",
        "Main path": "Parcours principal",
        "Review path": "Parcours de révision",
        "Teacher review first": "Validation enseignant d’abord",
        "Listening": "Compréhension orale",
        "Speaking": "Expression orale",
        "Reading": "Lecture",
        "Writing": "Écriture",
        "Grammar": "Grammaire",
        "Pronunciation": "Prononciation",
        "Vocabulary": "Vocabulaire",
        "Complete": "Terminé",
        "In Progress": "En cours",
        "Next": "Suivant",
        "Find Unit {$a}": "Trouver l’unité {$a}",
        "Go to Unit {$a}": "Aller à l’unité {$a}",
        "{$a} Listening Sample": "{$a} Exemple d’écoute",
        "Listen for the speaker's purpose in a {$a->language} {$a->level} dialogue.": "Écoutez l’objectif du locuteur dans un dialogue de {$a->language} niveau {$a->level}.",
        "Choose the best summary after listening twice.": "Choisissez le meilleur résumé après deux écoutes.",
        "{$a} Reading Sample": "{$a} Exemple de lecture",
        "Read a short {$a->language} text at {$a->level} and identify the main claim.": "Lisez un court texte en {$a->language} niveau {$a->level} et repérez l’idée principale.",
        "Select the sentence that best matches the writer's intention.": "Choisissez la phrase qui correspond le mieux à l’intention de l’auteur.",
        "{$a} Use of Language": "{$a} Usage de la langue",
        "Complete a {$a->level} grammar and vocabulary item for {$a->language}.": "Complétez un item de grammaire et de vocabulaire en {$a->language} niveau {$a->level}.",
        "Choose the answer that fits the context and register.": "Choisissez la réponse adaptée au contexte et au registre.",
        "{$a} Speaking or Writing": "{$a} Expression orale ou écrite",
        "Produce a short response for a {$a->language} {$a->level} situation.": "Produisez une courte réponse pour une situation en {$a->language} niveau {$a->level}.",
        "Record or draft a response, then compare it with the checklist.": "Enregistrez ou rédigez une réponse, puis comparez-la à la checklist.",
        "Recommended from your latest placement profile": "Recommandé à partir de votre dernier profil de positionnement",
        "Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.": "Commencez à l’unité recommandée, puis ajoutez des exercices de prononciation et d’écriture avant le prochain point de contrôle.",
        "Category description": "Description de la catégorie",
        "Primary and secondary school": "École primaire et secondaire",
        "Six-year courses for": "Cours de six ans pour",
        "School Course": "Cours scolaire",
        "No primary or secondary subcategories yet.": "Aucune sous-catégorie primaire ou secondaire pour le moment.",
        "University": "Université",
        "Two-year courses for": "Cours de deux ans pour",
        "University Course": "Cours universitaire",
        "No university subcategories yet.": "Aucune sous-catégorie universitaire pour le moment.",
        "school courses have been added yet.": "cours scolaires n’ont pas encore été ajoutés.",
    },
    "ja": {
        "Home": "ホーム",
        "Dashboard": "ダッシュボード",
        "Practice": "練習",
        "Dictionary": "辞書",
        "Exam": "試験",
        "Demo": "デモ",
        "Self Study": "自習",
        "Teacher": "教師",
        "English": "英語",
        "Russian": "ロシア語",
        "Chinese": "中国語",
        "Japanese": "日本語",
        "German": "ドイツ語",
        "French": "フランス語",
        "Spanish": "スペイン語",
        "Language": "言語",
        "CEFR level": "CEFR レベル",
        "Actions": "操作",
        "Attempts": "受験回数",
        "Ready": "準備完了",
        "My results": "自分の結果",
        "Find Self Exams": "自己試験を探す",
        "Teacher and Official Exam Sessions": "教師試験と公式試験セッション",
        "Self Exam Sessions": "自己試験セッション",
        "Start Self Exam": "自己試験を開始",
        "Start Teacher Exam": "教師試験を開始",
        "Start Official Exam": "公式試験を開始",
        "Learn a language by using it.": "使いながら言語を学びましょう。",
        "FLW word lab": "FLW 単語ラボ",
        "Multilingual dictionary": "多言語辞書",
        "Ready to start": "開始できます",
        "Browse courses": "コースを見る",
        "No courses yet": "コースはまだありません",
        "Open": "開く",
        "Open course": "コースを開く",
        "No activities yet": "アクティビティはまだありません",
        "Captions": "字幕",
        "{$a} confidence": "信頼度 {$a}",
        "Day streak": "連続学習日数",
        "{$a} day streak": "{$a} 日連続",
        "This week": "今週",
        "Placement": "プレイスメント",
        "Not placed": "未判定",
        "Provisional": "仮判定",
        "Main path with repair": "補強付きメインパス",
        "Main path": "メインパス",
        "Review path": "復習パス",
        "Teacher review first": "教師確認が先",
        "Listening": "リスニング",
        "Speaking": "スピーキング",
        "Reading": "リーディング",
        "Writing": "ライティング",
        "Grammar": "文法",
        "Pronunciation": "発音",
        "Vocabulary": "語彙",
        "Complete": "完了",
        "In Progress": "進行中",
        "Next": "次",
        "Find Unit {$a}": "ユニット {$a} を探す",
        "Go to Unit {$a}": "ユニット {$a} へ移動",
        "{$a} Listening Sample": "{$a} リスニングサンプル",
        "Listen for the speaker's purpose in a {$a->language} {$a->level} dialogue.": "{$a->language} {$a->level} の会話で、話し手の目的を聞き取ります。",
        "Choose the best summary after listening twice.": "2回聞いたあと、最もよい要約を選びます。",
        "{$a} Reading Sample": "{$a} リーディングサンプル",
        "Read a short {$a->language} text at {$a->level} and identify the main claim.": "{$a->language} {$a->level} の短い文章を読み、主張を見つけます。",
        "Select the sentence that best matches the writer's intention.": "書き手の意図に最も合う文を選びます。",
        "{$a} Use of Language": "{$a} 言語運用",
        "Complete a {$a->level} grammar and vocabulary item for {$a->language}.": "{$a->language} {$a->level} の文法と語彙問題を完成させます。",
        "Choose the answer that fits the context and register.": "文脈とレジスターに合う答えを選びます。",
        "{$a} Speaking or Writing": "{$a} スピーキングまたはライティング",
        "Produce a short response for a {$a->language} {$a->level} situation.": "{$a->language} {$a->level} の場面に対する短い返答を作ります。",
        "Record or draft a response, then compare it with the checklist.": "返答を録音または下書きし、チェックリストと比べます。",
        "Recommended from your latest placement profile": "最新のプレイスメントプロフィールに基づくおすすめ",
        "Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.": "おすすめのユニットから始め、次のチェックポイント前に発音とライティングの補強練習を追加します。",
        "Category description": "カテゴリ説明",
        "Primary and secondary school": "小学校・中学校・高校",
        "Six-year courses for": "6年間のコース:",
        "School Course": "学校コース",
        "No primary or secondary subcategories yet.": "小中高のサブカテゴリはまだありません。",
        "University": "大学",
        "Two-year courses for": "2年間のコース:",
        "University Course": "大学コース",
        "No university subcategories yet.": "大学のサブカテゴリはまだありません。",
        "school courses have been added yet.": "学校コースはまだ追加されていません。",
    },
    "ru": {
        "Home": "Главная",
        "Dashboard": "Панель",
        "Practice": "Практика",
        "Dictionary": "Словарь",
        "Exam": "Экзамен",
        "Demo": "Демо",
        "Self Study": "Самообучение",
        "Teacher": "Учитель",
        "English": "Английский",
        "Russian": "Русский",
        "Chinese": "Китайский",
        "Japanese": "Японский",
        "German": "Немецкий",
        "French": "Французский",
        "Spanish": "Испанский",
        "Language": "Язык",
        "CEFR level": "Уровень CEFR",
        "Actions": "Действия",
        "Attempts": "Попытки",
        "Ready": "Готово",
        "My results": "Мои результаты",
        "Find Self Exams": "Найти самостоятельные экзамены",
        "Teacher and Official Exam Sessions": "Учительские и официальные экзаменационные сессии",
        "Self Exam Sessions": "Самостоятельные экзаменационные сессии",
        "Start Self Exam": "Начать самостоятельный экзамен",
        "Start Teacher Exam": "Начать учительский экзамен",
        "Start Official Exam": "Начать официальный экзамен",
        "Learn a language by using it.": "Изучайте язык, используя его.",
        "FLW word lab": "FLW словарная лаборатория",
        "Multilingual dictionary": "Многоязычный словарь",
        "Ready to start": "Готово к началу",
        "Browse courses": "Просмотреть курсы",
        "No courses yet": "Курсов пока нет",
        "Open": "Открыть",
        "Open course": "Открыть курс",
        "No activities yet": "Активностей пока нет",
        "Captions": "Субтитры",
        "{$a} confidence": "уверенность {$a}",
        "Day streak": "Серия дней",
        "{$a} day streak": "{$a} дней подряд",
        "This week": "На этой неделе",
        "Placement": "Входное определение уровня",
        "Not placed": "Уровень не определен",
        "Provisional": "Предварительно",
        "Main path with repair": "Основной путь с доработкой",
        "Main path": "Основной путь",
        "Review path": "Путь повторения",
        "Teacher review first": "Сначала проверка учителем",
        "Listening": "Аудирование",
        "Speaking": "Говорение",
        "Reading": "Чтение",
        "Writing": "Письмо",
        "Grammar": "Грамматика",
        "Pronunciation": "Произношение",
        "Vocabulary": "Словарь",
        "Complete": "Завершено",
        "In Progress": "В процессе",
        "Next": "Далее",
        "Find Unit {$a}": "Найти раздел {$a}",
        "Go to Unit {$a}": "Перейти к разделу {$a}",
        "{$a} Listening Sample": "{$a} образец аудирования",
        "Listen for the speaker's purpose in a {$a->language} {$a->level} dialogue.": "Определите цель говорящего в диалоге на {$a->language} уровня {$a->level}.",
        "Choose the best summary after listening twice.": "Выберите лучшее краткое содержание после двух прослушиваний.",
        "{$a} Reading Sample": "{$a} образец чтения",
        "Read a short {$a->language} text at {$a->level} and identify the main claim.": "Прочитайте короткий текст на {$a->language} уровня {$a->level} и определите главную мысль.",
        "Select the sentence that best matches the writer's intention.": "Выберите предложение, которое лучше всего соответствует намерению автора.",
        "{$a} Use of Language": "{$a} использование языка",
        "Complete a {$a->level} grammar and vocabulary item for {$a->language}.": "Выполните задание по грамматике и лексике на {$a->language} уровня {$a->level}.",
        "Choose the answer that fits the context and register.": "Выберите ответ, подходящий по контексту и регистру.",
        "{$a} Speaking or Writing": "{$a} говорение или письмо",
        "Produce a short response for a {$a->language} {$a->level} situation.": "Подготовьте короткий ответ для ситуации на {$a->language} уровня {$a->level}.",
        "Record or draft a response, then compare it with the checklist.": "Запишите или набросайте ответ, затем сравните его с чеклистом.",
        "Recommended from your latest placement profile": "Рекомендовано на основе вашего последнего профиля входного уровня",
        "Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.": "Начните с рекомендованного раздела, затем добавьте упражнения на произношение и письмо перед следующим контрольным этапом.",
        "Category description": "Описание категории",
        "Primary and secondary school": "Начальная и средняя школа",
        "Six-year courses for": "Шестилетние курсы для",
        "School Course": "Школьный курс",
        "No primary or secondary subcategories yet.": "Подкатегорий начальной или средней школы пока нет.",
        "University": "Университет",
        "Two-year courses for": "Двухлетние курсы для",
        "University Course": "Университетский курс",
        "No university subcategories yet.": "Университетских подкатегорий пока нет.",
        "school courses have been added yet.": "школьные курсы еще не добавлены.",
    },
    "zh_cn": {
        "Home": "首页",
        "Dashboard": "仪表板",
        "Practice": "练习",
        "Dictionary": "词典",
        "Exam": "考试",
        "Demo": "演示",
        "Self Study": "自学",
        "Teacher": "教师",
        "English": "英语",
        "Russian": "俄语",
        "Chinese": "中文",
        "Japanese": "日语",
        "German": "德语",
        "French": "法语",
        "Spanish": "西班牙语",
        "Language": "语言",
        "CEFR level": "CEFR 等级",
        "Actions": "操作",
        "Attempts": "尝试",
        "Ready": "就绪",
        "My results": "我的结果",
        "Find Self Exams": "查找自主考试",
        "Teacher and Official Exam Sessions": "教师和官方考试场次",
        "Self Exam Sessions": "自主考试场次",
        "Start Self Exam": "开始自主考试",
        "Start Teacher Exam": "开始教师考试",
        "Start Official Exam": "开始官方考试",
        "Learn a language by using it.": "在使用中学习一门语言。",
        "FLW word lab": "FLW 单词实验室",
        "Multilingual dictionary": "多语言词典",
        "Ready to start": "准备开始",
        "Browse courses": "浏览课程",
        "No courses yet": "暂无课程",
        "Open": "打开",
        "Open course": "打开课程",
        "No activities yet": "暂无活动",
        "Captions": "字幕",
        "{$a} confidence": "{$a} 信心",
        "Day streak": "连续学习天数",
        "{$a} day streak": "连续 {$a} 天",
        "This week": "本周",
        "Placement": "水平定位",
        "Not placed": "未定位",
        "Provisional": "临时判定",
        "Main path with repair": "带补强的主路径",
        "Main path": "主路径",
        "Review path": "复习路径",
        "Teacher review first": "先由教师审核",
        "Listening": "听力",
        "Speaking": "口语",
        "Reading": "阅读",
        "Writing": "写作",
        "Grammar": "语法",
        "Pronunciation": "发音",
        "Vocabulary": "词汇",
        "Complete": "已完成",
        "In Progress": "进行中",
        "Next": "下一步",
        "Find Unit {$a}": "查找单元 {$a}",
        "Go to Unit {$a}": "前往单元 {$a}",
        "{$a} Listening Sample": "{$a} 听力样例",
        "Listen for the speaker's purpose in a {$a->language} {$a->level} dialogue.": "在 {$a->language} {$a->level} 对话中听出说话者的目的。",
        "Choose the best summary after listening twice.": "听两遍后选择最佳概要。",
        "{$a} Reading Sample": "{$a} 阅读样例",
        "Read a short {$a->language} text at {$a->level} and identify the main claim.": "阅读一篇 {$a->language} {$a->level} 短文，并找出主要观点。",
        "Select the sentence that best matches the writer's intention.": "选择最符合作者意图的句子。",
        "{$a} Use of Language": "{$a} 语言运用",
        "Complete a {$a->level} grammar and vocabulary item for {$a->language}.": "完成一道 {$a->language} {$a->level} 的语法和词汇题。",
        "Choose the answer that fits the context and register.": "选择符合语境和语体的答案。",
        "{$a} Speaking or Writing": "{$a} 口语或写作",
        "Produce a short response for a {$a->language} {$a->level} situation.": "针对一个 {$a->language} {$a->level} 情境做出简短回应。",
        "Record or draft a response, then compare it with the checklist.": "录制或起草回应，然后与检查清单进行比较。",
        "Recommended from your latest placement profile": "根据你最新的水平定位资料推荐",
        "Begin at the recommended unit, then add pronunciation and writing repair practice before the next checkpoint.": "从推荐单元开始，并在下一个检查点前加入发音和写作补强练习。",
        "Category description": "分类说明",
        "Primary and secondary school": "中小学",
        "Six-year courses for": "六年制课程：",
        "School Course": "学校课程",
        "No primary or secondary subcategories yet.": "暂无中小学子分类。",
        "University": "大学",
        "Two-year courses for": "两年制课程：",
        "University Course": "大学课程",
        "No university subcategories yet.": "暂无大学子分类。",
        "school courses have been added yet.": "尚未添加学校课程。",
    },
}


def php_unescape(value: str) -> str:
    return ast.literal_eval("'" + value.replace("\\'", "\\'") + "'")


def php_escape(value: str) -> str:
    value = re.sub(r"\s*\r?\n\s*", " ", value).strip()
    return value.replace("\\", "\\\\").replace("'", "\\'")


def parse_strings(path: Path):
    pattern = re.compile(r"^\$string\['((?:\\'|[^'])+)'\]\s*=\s*'((?:\\'|\\\\|[^'])*)';\s*$")
    entries = []
    for line in path.read_text(encoding="utf-8").splitlines():
        match = pattern.match(line)
        if match:
            entries.append((match.group(1).replace("\\'", "'"), php_unescape(match.group(2))))
    return entries


def preserve_tokens(text: str):
    replacements = []

    def repl(match):
        token = f"FLWPH{len(replacements)}TOKEN"
        replacements.append((token, match.group(0)))
        return token

    text = re.sub(r"\{\$a(?:->[A-Za-z0-9_]+)?\}", repl, text)
    for brand in BRAND_TOKENS:
        text = re.sub(rf"\b{re.escape(brand)}\b", repl, text)
    return text, replacements


def restore_tokens(text: str, replacements):
    for token, original in replacements:
        text = text.replace(token, original)
        text = text.replace(token.lower(), original)
        text = text.replace(token.capitalize(), original)
    return text


def should_translate(key: str, value: str) -> bool:
    if key in DO_NOT_TRANSLATE_KEYS:
        return False
    if any(key.startswith(prefix) for prefix in DO_NOT_TRANSLATE_PREFIXES):
        return False
    if value.strip() == "":
        return False
    if re.fullmatch(r"[-A-Z0-9_ .:%/]+", value):
        return False
    return True


def load_cache():
    if CACHE_PATH.exists():
        return json.loads(CACHE_PATH.read_text(encoding="utf-8"))
    return {}


def save_cache(cache):
    CACHE_PATH.write_text(json.dumps(cache, ensure_ascii=False, indent=2, sort_keys=True), encoding="utf-8")


def translate_batch(values, target, cache):
    if not values:
        return []
    results = []
    missing = []
    missing_indexes = []
    for index, value in enumerate(values):
        cache_key = target + "\n" + value
        if cache_key in cache:
            results.append(cache[cache_key])
        else:
            results.append(None)
            missing.append(value)
            missing_indexes.append(index)
    if not missing:
        return results

    marker = "|||FLW|||"
    packed = marker.join(missing)
    query = urllib.parse.urlencode({
        "client": "gtx",
        "sl": "en",
        "tl": target,
        "dt": "t",
        "q": packed,
    })
    url = "https://translate.googleapis.com/translate_a/single?" + query
    try:
        with urllib.request.urlopen(url, timeout=30) as response:
            payload = json.loads(response.read().decode("utf-8"))
        translated = "".join(part[0] for part in payload[0] if part and part[0])
    except Exception:
        for index, value in zip(missing_indexes, missing):
            single = translate_batch_uncached(value, target)
            results[index] = single
            cache[target + "\n" + value] = single
        save_cache(cache)
        return results
    parts = translated.split(marker)
    if len(parts) != len(missing):
        for index, value in zip(missing_indexes, missing):
            single = translate_batch_uncached(value, target)
            results[index] = single
            cache[target + "\n" + value] = single
        save_cache(cache)
        return results

    for index, value, part in zip(missing_indexes, missing, parts):
        cleaned = part.strip()
        results[index] = cleaned
        cache[target + "\n" + value] = cleaned
    save_cache(cache)
    return results


def translate_batch_uncached(value, target):
    query = urllib.parse.urlencode({
        "client": "gtx",
        "sl": "en",
        "tl": target,
        "dt": "t",
        "q": value,
    })
    url = "https://translate.googleapis.com/translate_a/single?" + query
    for attempt in range(3):
        try:
            with urllib.request.urlopen(url, timeout=30) as response:
                payload = json.loads(response.read().decode("utf-8"))
            return "".join(part[0] for part in payload[0] if part and part[0]).strip()
        except Exception:
            if attempt == 2:
                return value
            time.sleep(0.5 + attempt)


def translate_entries(entries, lang, target, cache):
    out = []
    pending = []
    pending_indexes = []
    preserved = {}
    exact = EXACT.get(lang, {})

    for index, (key, value) in enumerate(entries):
        if value in exact:
            out.append((key, exact[value]))
            continue
        if not should_translate(key, value):
            out.append((key, value))
            continue
        protected, replacements = preserve_tokens(value)
        out.append((key, None))
        pending.append(protected)
        pending_indexes.append(index)
        preserved[index] = replacements

    for offset in range(0, len(pending), 30):
        batch = pending[offset:offset + 30]
        indexes = pending_indexes[offset:offset + 30]
        translated = translate_batch(batch, target, cache)
        for index, text in zip(indexes, translated):
            key = entries[index][0]
            out[index] = (key, restore_tokens(text, preserved[index]))
        time.sleep(0.2)
    return out


def write_lang_file(path: Path, entries, source: Path):
    lines = [
        "<?php",
        "// This file is part of Moodle - http://moodle.org/",
        "// Generated from " + source.as_posix().replace(str(ROOT).replace("\\", "/") + "/", "") + ".",
        "",
    ]
    if source.name == "local_flwexam.php":
        lines.append("defined('MOODLE_INTERNAL') || die();")
        lines.append("")
    for key, value in entries:
        lines.append(f"$string['{php_escape(key)}'] = '{php_escape(value)}';")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main():
    cache = load_cache()
    for source, lang_root, filename in COMPONENTS:
        entries = parse_strings(source)
        for lang, target in LANGUAGES.items():
            translated = translate_entries(entries, lang, target, cache)
            write_lang_file(lang_root / lang / filename, translated, source)
            print(f"Wrote {lang_root / lang / filename}")


if __name__ == "__main__":
    main()
