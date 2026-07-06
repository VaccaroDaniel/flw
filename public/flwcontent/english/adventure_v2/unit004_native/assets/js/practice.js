const QUESTIONS = [
  {
    "id": "U4-L1-P01",
    "section": "L1",
    "type": "image_choice",
    "prompt": "Which action means Listen?",
    "choices": [
      "ear action",
      "eye action",
      "voice action"
    ],
    "answer": "ear action",
    "audio_text": "",
    "kp_tag": "command_recognition",
    "image_ref": "IMG003",
    "repair_note": "Listen uses your ears."
  },
  {
    "id": "U4-L1-P02",
    "section": "L1",
    "type": "image_choice",
    "prompt": "Which action means Look?",
    "choices": [
      "eye action",
      "finger action",
      "chair action"
    ],
    "answer": "eye action",
    "audio_text": "",
    "kp_tag": "command_recognition",
    "image_ref": "IMG003",
    "repair_note": "Look uses your eyes."
  },
  {
    "id": "U4-L1-P03",
    "section": "L1",
    "type": "image_choice",
    "prompt": "Which action means Say?",
    "choices": [
      "voice action",
      "ear action",
      "book action"
    ],
    "answer": "voice action",
    "audio_text": "",
    "kp_tag": "command_recognition",
    "image_ref": "IMG003",
    "repair_note": "Say uses your voice."
  },
  {
    "id": "U4-L1-P04",
    "section": "L1",
    "type": "audio_choice",
    "prompt": "Listen. Choose the command.",
    "choices": [
      "Listen.",
      "Look.",
      "Say."
    ],
    "answer": "Listen.",
    "audio_text": "Listen.",
    "kp_tag": "command_listening",
    "image_ref": "IMG004",
    "repair_note": "Replay the model and choose the command you hear."
  },
  {
    "id": "U4-L1-P05",
    "section": "L1",
    "type": "audio_choice",
    "prompt": "Listen. Choose the command.",
    "choices": [
      "Say.",
      "Listen.",
      "Look."
    ],
    "answer": "Look.",
    "audio_text": "Look.",
    "kp_tag": "command_listening",
    "image_ref": "IMG004",
    "repair_note": "Replay the model and choose the command you hear."
  },
  {
    "id": "U4-L1-P06",
    "section": "L1",
    "type": "audio_choice",
    "prompt": "Listen. Choose the command.",
    "choices": [
      "Look.",
      "Say.",
      "Listen."
    ],
    "answer": "Say.",
    "audio_text": "Say.",
    "kp_tag": "command_listening",
    "image_ref": "IMG004",
    "repair_note": "Replay the model and choose the command you hear."
  },
  {
    "id": "U4-L1-P07",
    "section": "L1",
    "type": "meaning_choice",
    "prompt": "Use your ears.",
    "choices": [
      "Listen.",
      "Look.",
      "Say."
    ],
    "answer": "Listen.",
    "audio_text": "",
    "kp_tag": "meaning",
    "image_ref": "IMG003",
    "repair_note": "Ears show listen."
  },
  {
    "id": "U4-L1-P08",
    "section": "L1",
    "type": "meaning_choice",
    "prompt": "Use your eyes.",
    "choices": [
      "Look.",
      "Point.",
      "Stop."
    ],
    "answer": "Look.",
    "audio_text": "",
    "kp_tag": "meaning",
    "image_ref": "IMG003",
    "repair_note": "Eyes show look."
  },
  {
    "id": "U4-L1-P09",
    "section": "L1",
    "type": "meaning_choice",
    "prompt": "Use your voice.",
    "choices": [
      "Say.",
      "Sit down.",
      "Write."
    ],
    "answer": "Say.",
    "audio_text": "",
    "kp_tag": "meaning",
    "image_ref": "IMG003",
    "repair_note": "Voice shows say."
  },
  {
    "id": "U4-L1-P10",
    "section": "L1",
    "type": "sequence_choice",
    "prompt": "Choose the model order.",
    "choices": [
      "Listen -> Look -> Say",
      "Say -> Sit -> Write",
      "Open -> Read -> Goodbye"
    ],
    "answer": "Listen -> Look -> Say",
    "audio_text": "",
    "kp_tag": "sequence",
    "image_ref": "IMG004",
    "repair_note": "The first lesson model is listen, look, say."
  },
  {
    "id": "U4-L1-P11",
    "section": "L1",
    "type": "polite_choice",
    "prompt": "Make it polite.",
    "choices": [
      "Listen, please.",
      "Listen book.",
      "Please listen book."
    ],
    "answer": "Listen, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG004",
    "repair_note": "Put please after the command."
  },
  {
    "id": "U4-L1-P12",
    "section": "L1",
    "type": "project_link",
    "prompt": "Which command can start your action routine?",
    "choices": [
      "Listen, please.",
      "I like animals.",
      "My family is big."
    ],
    "answer": "Listen, please.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG004",
    "repair_note": "Use only Unit 4 commands."
  },
  {
    "id": "U4-L2-P01",
    "section": "L2",
    "type": "meaning_choice",
    "prompt": "Ear icon means...",
    "choices": [
      "Listen.",
      "Look.",
      "Point."
    ],
    "answer": "Listen.",
    "audio_text": "",
    "kp_tag": "command_recognition",
    "image_ref": "IMG006",
    "repair_note": "Ear means listen."
  },
  {
    "id": "U4-L2-P02",
    "section": "L2",
    "type": "meaning_choice",
    "prompt": "Eyes icon means...",
    "choices": [
      "Look.",
      "Say.",
      "Sit down."
    ],
    "answer": "Look.",
    "audio_text": "",
    "kp_tag": "command_recognition",
    "image_ref": "IMG006",
    "repair_note": "Eyes mean look."
  },
  {
    "id": "U4-L2-P03",
    "section": "L2",
    "type": "meaning_choice",
    "prompt": "Finger icon means...",
    "choices": [
      "Point.",
      "Listen.",
      "Write."
    ],
    "answer": "Point.",
    "audio_text": "",
    "kp_tag": "command_recognition",
    "image_ref": "IMG006",
    "repair_note": "Finger means point."
  },
  {
    "id": "U4-L2-P04",
    "section": "L2",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Point.",
      "Look.",
      "Listen."
    ],
    "answer": "Point.",
    "audio_text": "Point.",
    "kp_tag": "command_listening",
    "image_ref": "IMG005",
    "repair_note": "The word begins with /p/."
  },
  {
    "id": "U4-L2-P05",
    "section": "L2",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Listen.",
      "Point.",
      "Say."
    ],
    "answer": "Listen.",
    "audio_text": "Listen.",
    "kp_tag": "command_listening",
    "image_ref": "IMG005",
    "repair_note": "Listen uses your ears."
  },
  {
    "id": "U4-L2-P06",
    "section": "L2",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Look.",
      "Write.",
      "Stop."
    ],
    "answer": "Look.",
    "audio_text": "Look.",
    "kp_tag": "command_listening",
    "image_ref": "IMG005",
    "repair_note": "Look uses your eyes."
  },
  {
    "id": "U4-L2-P07",
    "section": "L2",
    "type": "sequence_choice",
    "prompt": "Choose the trail order.",
    "choices": [
      "Listen -> Look -> Point",
      "Point -> Sit -> Read",
      "Look -> Write -> Stop"
    ],
    "answer": "Listen -> Look -> Point",
    "audio_text": "",
    "kp_tag": "sequence",
    "image_ref": "IMG007",
    "repair_note": "Follow the trail: listen, look, point."
  },
  {
    "id": "U4-L2-P08",
    "section": "L2",
    "type": "action_choice",
    "prompt": "Teacher says Point, please. What do you do?",
    "choices": [
      "point to a card",
      "close a book",
      "sit on a chair"
    ],
    "answer": "point to a card",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG005",
    "repair_note": "Point uses your finger."
  },
  {
    "id": "U4-L2-P09",
    "section": "L2",
    "type": "repair_choice",
    "prompt": "You chose Look for an ear icon. Fix it.",
    "choices": [
      "Listen.",
      "Look.",
      "Point."
    ],
    "answer": "Listen.",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG008",
    "repair_note": "Ear icon means listen."
  },
  {
    "id": "U4-L2-P10",
    "section": "L2",
    "type": "polite_choice",
    "prompt": "Choose the polite command.",
    "choices": [
      "Point, please.",
      "Point now you.",
      "Please point book you."
    ],
    "answer": "Point, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG006",
    "repair_note": "Use command plus please."
  },
  {
    "id": "U4-L2-P11",
    "section": "L2",
    "type": "contrast_choice",
    "prompt": "Which two commands are different?",
    "choices": [
      "Look and Point",
      "Look and Look",
      "Point and Point"
    ],
    "answer": "Look and Point",
    "audio_text": "",
    "kp_tag": "contrast",
    "image_ref": "IMG006",
    "repair_note": "Look uses eyes; point uses finger."
  },
  {
    "id": "U4-L2-P12",
    "section": "L2",
    "type": "project_link",
    "prompt": "Choose three safe project actions.",
    "choices": [
      "Listen, Look, Point",
      "Animals, Family, Country",
      "Age, Like, Map"
    ],
    "answer": "Listen, Look, Point",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG007",
    "repair_note": "Use Unit 4 classroom commands."
  },
  {
    "id": "U4-L3-P01",
    "section": "L3",
    "type": "meaning_choice",
    "prompt": "Say it again means...",
    "choices": [
      "repeat the word",
      "close the book",
      "stand up"
    ],
    "answer": "repeat the word",
    "audio_text": "",
    "kp_tag": "repeat_language",
    "image_ref": "IMG010",
    "repair_note": "Again means repeat."
  },
  {
    "id": "U4-L3-P02",
    "section": "L3",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Say.",
      "Sit down.",
      "Read."
    ],
    "answer": "Say.",
    "audio_text": "Say.",
    "kp_tag": "command_listening",
    "image_ref": "IMG009",
    "repair_note": "Say uses your voice."
  },
  {
    "id": "U4-L3-P03",
    "section": "L3",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Say it again.",
      "Open your book.",
      "Point."
    ],
    "answer": "Say it again.",
    "audio_text": "Say it again.",
    "kp_tag": "repeat_language",
    "image_ref": "IMG009",
    "repair_note": "The model asks for repetition."
  },
  {
    "id": "U4-L3-P04",
    "section": "L3",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "One more time.",
      "Close your book.",
      "Stop."
    ],
    "answer": "One more time.",
    "audio_text": "One more time.",
    "kp_tag": "repeat_language",
    "image_ref": "IMG010",
    "repair_note": "One more time means repeat once."
  },
  {
    "id": "U4-L3-P05",
    "section": "L3",
    "type": "dialogue_order",
    "prompt": "Choose the best dialogue order.",
    "choices": [
      "Mia: Hello. -> Emma: Hello. -> Leo: Say it again.",
      "Leo: Close book. -> Mia: Family. -> Emma: Map.",
      "Toto: Read. -> Teacher: Country. -> Mia: Age."
    ],
    "answer": "Mia: Hello. -> Emma: Hello. -> Leo: Say it again.",
    "audio_text": "",
    "kp_tag": "dialogue",
    "image_ref": "IMG009",
    "repair_note": "The lesson is repeat practice."
  },
  {
    "id": "U4-L3-P06",
    "section": "L3",
    "type": "response_choice",
    "prompt": "Teacher says Say hello. What do you say?",
    "choices": [
      "Hello.",
      "Open.",
      "Point."
    ],
    "answer": "Hello.",
    "audio_text": "",
    "kp_tag": "speaking_response",
    "image_ref": "IMG009",
    "repair_note": "Say hello means use your voice."
  },
  {
    "id": "U4-L3-P07",
    "section": "L3",
    "type": "response_choice",
    "prompt": "Teacher says Say it again. What do you do?",
    "choices": [
      "repeat the word",
      "draw a map",
      "close the chair"
    ],
    "answer": "repeat the word",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG010",
    "repair_note": "Again means repeat."
  },
  {
    "id": "U4-L3-P08",
    "section": "L3",
    "type": "phrase_choice",
    "prompt": "Choose a repeat phrase.",
    "choices": [
      "One more time.",
      "My country.",
      "I like dogs."
    ],
    "answer": "One more time.",
    "audio_text": "",
    "kp_tag": "repeat_language",
    "image_ref": "IMG010",
    "repair_note": "One more time is classroom repeat language."
  },
  {
    "id": "U4-L3-P09",
    "section": "L3",
    "type": "repair_choice",
    "prompt": "You did not hear the word. Choose a helpful phrase.",
    "choices": [
      "Say it again.",
      "I am seven.",
      "It is a map."
    ],
    "answer": "Say it again.",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG010",
    "repair_note": "Ask to hear it again."
  },
  {
    "id": "U4-L3-P10",
    "section": "L3",
    "type": "polite_choice",
    "prompt": "Make it kind.",
    "choices": [
      "Say it again, please.",
      "Say again you.",
      "Please again it say."
    ],
    "answer": "Say it again, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG010",
    "repair_note": "Use the fixed polite chunk."
  },
  {
    "id": "U4-L3-P11",
    "section": "L3",
    "type": "boundary_choice",
    "prompt": "Which phrase is too difficult for Unit 4?",
    "choices": [
      "Can you repeat the instruction for me?",
      "Say it again.",
      "One more time."
    ],
    "answer": "Can you repeat the instruction for me?",
    "audio_text": "",
    "kp_tag": "KP_boundary",
    "image_ref": "IMG010",
    "repair_note": "Unit 4 uses short chunks."
  },
  {
    "id": "U4-L3-P12",
    "section": "L3",
    "type": "project_link",
    "prompt": "Which line can help your recording?",
    "choices": [
      "Say it again.",
      "My sister is ten.",
      "I like rabbits."
    ],
    "answer": "Say it again.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG009",
    "repair_note": "Repeat language helps you practise the project."
  },
  {
    "id": "U4-L4-P01",
    "section": "L4",
    "type": "image_choice",
    "prompt": "Choose Stand up.",
    "choices": [
      "standing child",
      "sitting child",
      "open book"
    ],
    "answer": "standing child",
    "audio_text": "",
    "kp_tag": "action_recognition",
    "image_ref": "IMG011",
    "repair_note": "Stand up means body is standing."
  },
  {
    "id": "U4-L4-P02",
    "section": "L4",
    "type": "image_choice",
    "prompt": "Choose Sit down.",
    "choices": [
      "sitting child",
      "pointing child",
      "writing child"
    ],
    "answer": "sitting child",
    "audio_text": "",
    "kp_tag": "action_recognition",
    "image_ref": "IMG012",
    "repair_note": "Sit down means body is seated."
  },
  {
    "id": "U4-L4-P03",
    "section": "L4",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Stand up.",
      "Sit down.",
      "Read."
    ],
    "answer": "Stand up.",
    "audio_text": "Stand up.",
    "kp_tag": "command_listening",
    "image_ref": "IMG011",
    "repair_note": "Stand up begins with stand."
  },
  {
    "id": "U4-L4-P04",
    "section": "L4",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Sit down.",
      "Look.",
      "Open your book."
    ],
    "answer": "Sit down.",
    "audio_text": "Sit down.",
    "kp_tag": "command_listening",
    "image_ref": "IMG012",
    "repair_note": "Sit down begins with sit."
  },
  {
    "id": "U4-L4-P05",
    "section": "L4",
    "type": "contrast_choice",
    "prompt": "Which pair is correct?",
    "choices": [
      "Stand up = standing / Sit down = sitting",
      "Stand up = reading / Sit down = writing",
      "Stand up = open / Sit down = close"
    ],
    "answer": "Stand up = standing / Sit down = sitting",
    "audio_text": "",
    "kp_tag": "contrast",
    "image_ref": "IMG013",
    "repair_note": "Use the contrast board."
  },
  {
    "id": "U4-L4-P06",
    "section": "L4",
    "type": "sequence_choice",
    "prompt": "Choose the action order.",
    "choices": [
      "Stand up -> Sit down -> Stop",
      "Read -> Map -> Family",
      "Point -> Country -> Age"
    ],
    "answer": "Stand up -> Sit down -> Stop",
    "audio_text": "",
    "kp_tag": "sequence",
    "image_ref": "IMG013",
    "repair_note": "This lesson uses body actions."
  },
  {
    "id": "U4-L4-P07",
    "section": "L4",
    "type": "action_choice",
    "prompt": "Teacher says Stand up, please. What do you do?",
    "choices": [
      "stand beside the chair",
      "close the book",
      "write a card"
    ],
    "answer": "stand beside the chair",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG011",
    "repair_note": "Stand beside the chair."
  },
  {
    "id": "U4-L4-P08",
    "section": "L4",
    "type": "action_choice",
    "prompt": "Teacher says Sit down, please. What do you do?",
    "choices": [
      "sit on the chair",
      "point to a card",
      "say goodbye"
    ],
    "answer": "sit on the chair",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG012",
    "repair_note": "Sit on the chair."
  },
  {
    "id": "U4-L4-P09",
    "section": "L4",
    "type": "polite_choice",
    "prompt": "Choose the polite command.",
    "choices": [
      "Sit down, please.",
      "Down sit you.",
      "Please down sit."
    ],
    "answer": "Sit down, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG012",
    "repair_note": "Use command plus please."
  },
  {
    "id": "U4-L4-P10",
    "section": "L4",
    "type": "true_false",
    "prompt": "Stand up and sit down are the same action.",
    "choices": [
      "False",
      "True"
    ],
    "answer": "False",
    "audio_text": "",
    "kp_tag": "contrast",
    "image_ref": "IMG013",
    "repair_note": "They are opposite body actions."
  },
  {
    "id": "U4-L4-P11",
    "section": "L4",
    "type": "repair_choice",
    "prompt": "You mixed up stand and sit. Which image helps?",
    "choices": [
      "Stand/Sit contrast board",
      "Read/Write board",
      "Open/Close board"
    ],
    "answer": "Stand/Sit contrast board",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG013",
    "repair_note": "Use the stand/sit board."
  },
  {
    "id": "U4-L4-P12",
    "section": "L4",
    "type": "project_link",
    "prompt": "Choose two commands for your routine.",
    "choices": [
      "Stand up, please. Sit down, please.",
      "I like cats. I am eight.",
      "My country. My map."
    ],
    "answer": "Stand up, please. Sit down, please.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG011",
    "repair_note": "Use body action commands."
  },
  {
    "id": "U4-L5-P01",
    "section": "L5",
    "type": "image_choice",
    "prompt": "Choose Open your book.",
    "choices": [
      "open book",
      "closed book",
      "standing child"
    ],
    "answer": "open book",
    "audio_text": "",
    "kp_tag": "action_recognition",
    "image_ref": "IMG014",
    "repair_note": "Open book shows pages."
  },
  {
    "id": "U4-L5-P02",
    "section": "L5",
    "type": "image_choice",
    "prompt": "Choose Close your book.",
    "choices": [
      "closed book",
      "pointing finger",
      "reading eyes"
    ],
    "answer": "closed book",
    "audio_text": "",
    "kp_tag": "action_recognition",
    "image_ref": "IMG015",
    "repair_note": "Closed book is shut."
  },
  {
    "id": "U4-L5-P03",
    "section": "L5",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Open your book.",
      "Close your book.",
      "Stand up."
    ],
    "answer": "Open your book.",
    "audio_text": "Open your book.",
    "kp_tag": "command_listening",
    "image_ref": "IMG014",
    "repair_note": "Open is the action you hear."
  },
  {
    "id": "U4-L5-P04",
    "section": "L5",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Close your book.",
      "Look.",
      "Say it again."
    ],
    "answer": "Close your book.",
    "audio_text": "Close your book.",
    "kp_tag": "command_listening",
    "image_ref": "IMG015",
    "repair_note": "Close is the action you hear."
  },
  {
    "id": "U4-L5-P05",
    "section": "L5",
    "type": "contrast_choice",
    "prompt": "Which pair is correct?",
    "choices": [
      "Open = pages out / Close = book shut",
      "Open = sit / Close = stand",
      "Open = ear / Close = eyes"
    ],
    "answer": "Open = pages out / Close = book shut",
    "audio_text": "",
    "kp_tag": "contrast",
    "image_ref": "IMG016",
    "repair_note": "Use the open/close board."
  },
  {
    "id": "U4-L5-P06",
    "section": "L5",
    "type": "phrase_completion",
    "prompt": "Open your ___.",
    "choices": [
      "book",
      "chair",
      "voice"
    ],
    "answer": "book",
    "audio_text": "",
    "kp_tag": "form",
    "image_ref": "IMG014",
    "repair_note": "The fixed chunk is Open your book."
  },
  {
    "id": "U4-L5-P07",
    "section": "L5",
    "type": "phrase_completion",
    "prompt": "Close your ___.",
    "choices": [
      "book",
      "finger",
      "eyes"
    ],
    "answer": "book",
    "audio_text": "",
    "kp_tag": "form",
    "image_ref": "IMG015",
    "repair_note": "The fixed chunk is Close your book."
  },
  {
    "id": "U4-L5-P08",
    "section": "L5",
    "type": "polite_choice",
    "prompt": "Choose the polite command.",
    "choices": [
      "Open your book, please.",
      "Book open please your.",
      "Open you book."
    ],
    "answer": "Open your book, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG014",
    "repair_note": "Use command plus please."
  },
  {
    "id": "U4-L5-P09",
    "section": "L5",
    "type": "action_choice",
    "prompt": "Teacher says Close your book. What changes?",
    "choices": [
      "open pages become shut",
      "student stands up",
      "student points"
    ],
    "answer": "open pages become shut",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG015",
    "repair_note": "Close means shut the book."
  },
  {
    "id": "U4-L5-P10",
    "section": "L5",
    "type": "sequence_choice",
    "prompt": "Choose the classroom order.",
    "choices": [
      "Open your book -> Read -> Close your book",
      "Close -> Country -> Family",
      "Sit -> Map -> Animal"
    ],
    "answer": "Open your book -> Read -> Close your book",
    "audio_text": "",
    "kp_tag": "sequence",
    "image_ref": "IMG016",
    "repair_note": "Open, read, close is a classroom routine."
  },
  {
    "id": "U4-L5-P11",
    "section": "L5",
    "type": "repair_choice",
    "prompt": "You chose open for a closed book. Fix it.",
    "choices": [
      "Close your book.",
      "Open your book.",
      "Listen."
    ],
    "answer": "Close your book.",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG016",
    "repair_note": "Closed book means close."
  },
  {
    "id": "U4-L5-P12",
    "section": "L5",
    "type": "project_link",
    "prompt": "Choose one object action for the final routine.",
    "choices": [
      "Open your book, please.",
      "I like apples.",
      "My family is here."
    ],
    "answer": "Open your book, please.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG014",
    "repair_note": "Use a classroom object action."
  },
  {
    "id": "U4-L6-P01",
    "section": "L6",
    "type": "image_choice",
    "prompt": "Choose Read.",
    "choices": [
      "eyes on card",
      "pencil on card",
      "standing child"
    ],
    "answer": "eyes on card",
    "audio_text": "",
    "kp_tag": "action_recognition",
    "image_ref": "IMG017",
    "repair_note": "Read uses eyes on a card or book."
  },
  {
    "id": "U4-L6-P02",
    "section": "L6",
    "type": "image_choice",
    "prompt": "Choose Write.",
    "choices": [
      "pencil on card",
      "ear icon",
      "closed book"
    ],
    "answer": "pencil on card",
    "audio_text": "",
    "kp_tag": "action_recognition",
    "image_ref": "IMG018",
    "repair_note": "Write uses a pencil."
  },
  {
    "id": "U4-L6-P03",
    "section": "L6",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Read.",
      "Write.",
      "Stop."
    ],
    "answer": "Read.",
    "audio_text": "Read.",
    "kp_tag": "command_listening",
    "image_ref": "IMG017",
    "repair_note": "Read is the command you hear."
  },
  {
    "id": "U4-L6-P04",
    "section": "L6",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Write.",
      "Look.",
      "Sit down."
    ],
    "answer": "Write.",
    "audio_text": "Write.",
    "kp_tag": "command_listening",
    "image_ref": "IMG018",
    "repair_note": "Write is the command you hear."
  },
  {
    "id": "U4-L6-P05",
    "section": "L6",
    "type": "contrast_choice",
    "prompt": "Which pair is correct?",
    "choices": [
      "Read = eyes / Write = pencil",
      "Read = chair / Write = dog",
      "Read = ear / Write = stand"
    ],
    "answer": "Read = eyes / Write = pencil",
    "audio_text": "",
    "kp_tag": "contrast",
    "image_ref": "IMG019",
    "repair_note": "Use the notebook board."
  },
  {
    "id": "U4-L6-P06",
    "section": "L6",
    "type": "phrase_choice",
    "prompt": "Teacher says Read, please. What do you do?",
    "choices": [
      "look at the card/book",
      "close your book",
      "stand beside chair"
    ],
    "answer": "look at the card/book",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG017",
    "repair_note": "Read means look at text or card."
  },
  {
    "id": "U4-L6-P07",
    "section": "L6",
    "type": "phrase_choice",
    "prompt": "Teacher says Write, please. What do you do?",
    "choices": [
      "use a pencil",
      "point to Toto",
      "say goodbye"
    ],
    "answer": "use a pencil",
    "audio_text": "",
    "kp_tag": "action_response",
    "image_ref": "IMG018",
    "repair_note": "Write means use pencil on paper/card."
  },
  {
    "id": "U4-L6-P08",
    "section": "L6",
    "type": "polite_choice",
    "prompt": "Choose the polite command.",
    "choices": [
      "Write, please.",
      "Please writing.",
      "You write please it."
    ],
    "answer": "Write, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG018",
    "repair_note": "Short command plus please."
  },
  {
    "id": "U4-L6-P09",
    "section": "L6",
    "type": "sequence_choice",
    "prompt": "Choose a good work order.",
    "choices": [
      "Read -> Write",
      "Write -> Sit -> Country",
      "Look -> Animal -> Family"
    ],
    "answer": "Read -> Write",
    "audio_text": "",
    "kp_tag": "sequence",
    "image_ref": "IMG019",
    "repair_note": "Read before writing is a simple classroom order."
  },
  {
    "id": "U4-L6-P10",
    "section": "L6",
    "type": "repair_choice",
    "prompt": "You mixed up read and write. Which clue helps?",
    "choices": [
      "eyes for read, pencil for write",
      "chair for read, dog for write",
      "map for read, country for write"
    ],
    "answer": "eyes for read, pencil for write",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG019",
    "repair_note": "Eyes and pencil are the key cues."
  },
  {
    "id": "U4-L6-P11",
    "section": "L6",
    "type": "copy_choice",
    "prompt": "Which label can you copy for this lesson?",
    "choices": [
      "Read.",
      "I like animals.",
      "My country is big."
    ],
    "answer": "Read.",
    "audio_text": "",
    "kp_tag": "writing_support",
    "image_ref": "IMG019",
    "repair_note": "Copy only Unit 4 command labels."
  },
  {
    "id": "U4-L6-P12",
    "section": "L6",
    "type": "project_link",
    "prompt": "Choose a command for your routine.",
    "choices": [
      "Write, please.",
      "I am from Canada.",
      "I like cats."
    ],
    "answer": "Write, please.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG018",
    "repair_note": "Use Unit 4 command language."
  },
  {
    "id": "U4-L7-P01",
    "section": "L7",
    "type": "review_choice",
    "prompt": "Choose a Unit 4 command.",
    "choices": [
      "Listen, please.",
      "I like rabbits.",
      "My sister is ten."
    ],
    "answer": "Listen, please.",
    "audio_text": "",
    "kp_tag": "review",
    "image_ref": "IMG020",
    "repair_note": "Use classroom command language."
  },
  {
    "id": "U4-L7-P02",
    "section": "L7",
    "type": "review_choice",
    "prompt": "Choose a Unit 4 command.",
    "choices": [
      "Open your book, please.",
      "I am from London.",
      "I have a cat."
    ],
    "answer": "Open your book, please.",
    "audio_text": "",
    "kp_tag": "review",
    "image_ref": "IMG020",
    "repair_note": "Use classroom command language."
  },
  {
    "id": "U4-L7-P03",
    "section": "L7",
    "type": "review_choice",
    "prompt": "Choose a Unit 4 command.",
    "choices": [
      "Write, please.",
      "My favorite color is old.",
      "This is my mother."
    ],
    "answer": "Write, please.",
    "audio_text": "",
    "kp_tag": "review",
    "image_ref": "IMG020",
    "repair_note": "Use classroom command language."
  },
  {
    "id": "U4-L7-P04",
    "section": "L7",
    "type": "sequence_choice",
    "prompt": "Choose four good project commands.",
    "choices": [
      "Listen -> Point -> Stand up -> Open your book",
      "Family -> Country -> Animal -> Age",
      "Map -> Sister -> Like -> Plural"
    ],
    "answer": "Listen -> Point -> Stand up -> Open your book",
    "audio_text": "",
    "kp_tag": "sequence",
    "image_ref": "IMG020",
    "repair_note": "Final project uses four commands."
  },
  {
    "id": "U4-L7-P05",
    "section": "L7",
    "type": "polite_choice",
    "prompt": "Which line uses please correctly?",
    "choices": [
      "Stand up, please.",
      "Please up stand you.",
      "Stand please up your."
    ],
    "answer": "Stand up, please.",
    "audio_text": "",
    "kp_tag": "politeness",
    "image_ref": "IMG020",
    "repair_note": "Command plus please."
  },
  {
    "id": "U4-L7-P06",
    "section": "L7",
    "type": "project_check",
    "prompt": "Your routine has three commands. What is missing?",
    "choices": [
      "one more command",
      "a country",
      "an animal opinion"
    ],
    "answer": "one more command",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG022",
    "repair_note": "The project needs four commands."
  },
  {
    "id": "U4-L7-P07",
    "section": "L7",
    "type": "project_check",
    "prompt": "Your card says Listen, Look, Point, Write. Is it Unit 4 language?",
    "choices": [
      "Yes",
      "No"
    ],
    "answer": "Yes",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG022",
    "repair_note": "All four are Unit 4 commands."
  },
  {
    "id": "U4-L7-P08",
    "section": "L7",
    "type": "project_check",
    "prompt": "Which line is off-scope?",
    "choices": [
      "I like dogs.",
      "Sit down, please.",
      "Read, please."
    ],
    "answer": "I like dogs.",
    "audio_text": "",
    "kp_tag": "KP_boundary",
    "image_ref": "IMG020",
    "repair_note": "Likes and animals come later."
  },
  {
    "id": "U4-L7-P09",
    "section": "L7",
    "type": "repair_choice",
    "prompt": "You used a long sentence. Choose a shorter Unit 4 line.",
    "choices": [
      "Read, please.",
      "Can you please read the classroom instruction slowly?",
      "I am reading because I like books."
    ],
    "answer": "Read, please.",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG020",
    "repair_note": "Keep output short at Pre-A1.1."
  },
  {
    "id": "U4-L7-P10",
    "section": "L7",
    "type": "speaking_choice",
    "prompt": "Which line is easy to record clearly?",
    "choices": [
      "Close your book, please.",
      "Could everyone close those books immediately?",
      "I close closed closing book."
    ],
    "answer": "Close your book, please.",
    "audio_text": "",
    "kp_tag": "speaking",
    "image_ref": "IMG022",
    "repair_note": "Use the fixed model chunk."
  },
  {
    "id": "U4-L7-P11",
    "section": "L7",
    "type": "evidence_choice",
    "prompt": "Choose the complete project evidence.",
    "choices": [
      "four-card strip + voice/video recording + feedback note",
      "one picture only",
      "a long grammar table"
    ],
    "answer": "four-card strip + voice/video recording + feedback note",
    "audio_text": "",
    "kp_tag": "portfolio",
    "image_ref": "IMG022",
    "repair_note": "Portfolio needs product, recording, and feedback."
  },
  {
    "id": "U4-L7-P12",
    "section": "L7",
    "type": "final_gate",
    "prompt": "Choose the best project script.",
    "choices": [
      "Hello. I'm ___. Listen, please. Stand up, please. Sit down, please. Goodbye.",
      "Hello. I like dogs. My sister is ten. I live in a big city.",
      "This grammar explains imperatives and modal verbs."
    ],
    "answer": "Hello. I'm ___. Listen, please. Stand up, please. Sit down, please. Goodbye.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG022",
    "repair_note": "Keep it short and Unit 4 only."
  },
  {
    "id": "U4-WATCH-P01",
    "section": "WATCH",
    "type": "gist_choice",
    "prompt": "What is the video about?",
    "choices": [
      "classroom action commands",
      "animal likes",
      "family names"
    ],
    "answer": "classroom action commands",
    "audio_text": "",
    "kp_tag": "watch_gist",
    "image_ref": "IMG021",
    "repair_note": "The Watch section reviews classroom commands."
  },
  {
    "id": "U4-WATCH-P02",
    "section": "WATCH",
    "type": "detail_choice",
    "prompt": "The teacher first says...",
    "choices": [
      "Listen, please.",
      "I like animals.",
      "What country?"
    ],
    "answer": "Listen, please.",
    "audio_text": "",
    "kp_tag": "watch_detail",
    "image_ref": "IMG021",
    "repair_note": "The first line is Listen, please."
  },
  {
    "id": "U4-WATCH-P03",
    "section": "WATCH",
    "type": "detail_choice",
    "prompt": "Leo says...",
    "choices": [
      "Point, please.",
      "Close your family.",
      "I am eight."
    ],
    "answer": "Point, please.",
    "audio_text": "",
    "kp_tag": "watch_detail",
    "image_ref": "IMG021",
    "repair_note": "Leo says Point, please."
  },
  {
    "id": "U4-WATCH-P04",
    "section": "WATCH",
    "type": "detail_choice",
    "prompt": "Mia does which action?",
    "choices": [
      "stands up",
      "writes a map",
      "counts animals"
    ],
    "answer": "stands up",
    "audio_text": "",
    "kp_tag": "watch_detail",
    "image_ref": "IMG021",
    "repair_note": "Mia stands up."
  },
  {
    "id": "U4-WATCH-P05",
    "section": "WATCH",
    "type": "detail_choice",
    "prompt": "Emma does which action?",
    "choices": [
      "sits down",
      "opens a country",
      "likes a rabbit"
    ],
    "answer": "sits down",
    "audio_text": "",
    "kp_tag": "watch_detail",
    "image_ref": "IMG021",
    "repair_note": "Emma sits down."
  },
  {
    "id": "U4-WATCH-P06",
    "section": "WATCH",
    "type": "detail_choice",
    "prompt": "The teacher says...",
    "choices": [
      "Open your book, please.",
      "Open your sister, please.",
      "Open your animal, please."
    ],
    "answer": "Open your book, please.",
    "audio_text": "",
    "kp_tag": "watch_detail",
    "image_ref": "IMG021",
    "repair_note": "The object is book."
  },
  {
    "id": "U4-WATCH-P07",
    "section": "WATCH",
    "type": "sequence_choice",
    "prompt": "Choose the Watch order.",
    "choices": [
      "Listen -> Look -> Point -> Stand up -> Sit down",
      "Read -> Family -> Age -> Country",
      "Animal -> Like -> Map -> Sister"
    ],
    "answer": "Listen -> Look -> Point -> Stand up -> Sit down",
    "audio_text": "",
    "kp_tag": "watch_sequence",
    "image_ref": "IMG021",
    "repair_note": "The video follows the command routine."
  },
  {
    "id": "U4-WATCH-P08",
    "section": "WATCH",
    "type": "phrase_completion",
    "prompt": "Close your ___.",
    "choices": [
      "book",
      "chair",
      "voice"
    ],
    "answer": "book",
    "audio_text": "",
    "kp_tag": "watch_language",
    "image_ref": "IMG021",
    "repair_note": "Close your book is the unit chunk."
  },
  {
    "id": "U4-WATCH-P09",
    "section": "WATCH",
    "type": "audio_choice",
    "prompt": "Listen and choose.",
    "choices": [
      "Classroom magic!",
      "Animal friends!",
      "Family tree!"
    ],
    "answer": "Classroom magic!",
    "audio_text": "Classroom magic!",
    "kp_tag": "watch_listening",
    "image_ref": "IMG021",
    "repair_note": "The video ends with Classroom magic."
  },
  {
    "id": "U4-WATCH-P10",
    "section": "WATCH",
    "type": "true_false",
    "prompt": "The Watch video uses Unit 4 commands.",
    "choices": [
      "True",
      "False"
    ],
    "answer": "True",
    "audio_text": "",
    "kp_tag": "watch_gist",
    "image_ref": "IMG021",
    "repair_note": "It reviews Unit 4."
  },
  {
    "id": "U4-WATCH-P11",
    "section": "WATCH",
    "type": "project_link",
    "prompt": "Which Watch line can go into your project?",
    "choices": [
      "Stand up, please.",
      "I like cats.",
      "My father is tall."
    ],
    "answer": "Stand up, please.",
    "audio_text": "",
    "kp_tag": "project_readiness",
    "image_ref": "IMG021",
    "repair_note": "Use Watch language in the project."
  },
  {
    "id": "U4-WATCH-P12",
    "section": "WATCH",
    "type": "repair_choice",
    "prompt": "You missed the order. What should you do?",
    "choices": [
      "Replay and match each action card.",
      "Skip the unit.",
      "Add new grammar."
    ],
    "answer": "Replay and match each action card.",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG021",
    "repair_note": "Replay supports listening."
  },
  {
    "id": "U4-PROJECT-P01",
    "section": "PROJECT",
    "type": "project_choice",
    "prompt": "Choose the project title.",
    "choices": [
      "My Classroom Action Recording",
      "My Animal Poster",
      "My Family Tree"
    ],
    "answer": "My Classroom Action Recording",
    "audio_text": "",
    "kp_tag": "project_identity",
    "image_ref": "IMG022",
    "repair_note": "Unit 4 project is action recording."
  },
  {
    "id": "U4-PROJECT-P02",
    "section": "PROJECT",
    "type": "project_choice",
    "prompt": "How many commands do you need?",
    "choices": [
      "four",
      "one",
      "ten long sentences"
    ],
    "answer": "four",
    "audio_text": "",
    "kp_tag": "project_requirements",
    "image_ref": "IMG022",
    "repair_note": "The project requires four commands."
  },
  {
    "id": "U4-PROJECT-P03",
    "section": "PROJECT",
    "type": "script_choice",
    "prompt": "Choose a good opening.",
    "choices": [
      "Hello. I'm ___.",
      "I like three rabbits.",
      "This is a country map."
    ],
    "answer": "Hello. I'm ___.",
    "audio_text": "",
    "kp_tag": "review",
    "image_ref": "IMG022",
    "repair_note": "Use Unit 1 review opening."
  },
  {
    "id": "U4-PROJECT-P04",
    "section": "PROJECT",
    "type": "script_choice",
    "prompt": "Choose a good command.",
    "choices": [
      "Listen, please.",
      "I am from Russia.",
      "My brother is seven."
    ],
    "answer": "Listen, please.",
    "audio_text": "",
    "kp_tag": "project_language",
    "image_ref": "IMG022",
    "repair_note": "Use a Unit 4 command."
  },
  {
    "id": "U4-PROJECT-P05",
    "section": "PROJECT",
    "type": "script_choice",
    "prompt": "Choose a good command.",
    "choices": [
      "Open your book, please.",
      "I like bananas.",
      "This is my mother."
    ],
    "answer": "Open your book, please.",
    "audio_text": "",
    "kp_tag": "project_language",
    "image_ref": "IMG022",
    "repair_note": "Use a Unit 4 command."
  },
  {
    "id": "U4-PROJECT-P06",
    "section": "PROJECT",
    "type": "script_choice",
    "prompt": "Choose a good ending.",
    "choices": [
      "Goodbye.",
      "My phone number is five.",
      "The imperative mood is simple."
    ],
    "answer": "Goodbye.",
    "audio_text": "",
    "kp_tag": "review",
    "image_ref": "IMG022",
    "repair_note": "Use Unit 1 review ending."
  },
  {
    "id": "U4-PROJECT-P07",
    "section": "PROJECT",
    "type": "evidence_choice",
    "prompt": "What should you submit?",
    "choices": [
      "action card image and recording",
      "only a grammar note",
      "only a map"
    ],
    "answer": "action card image and recording",
    "audio_text": "",
    "kp_tag": "portfolio",
    "image_ref": "IMG022",
    "repair_note": "Project evidence needs card plus recording."
  },
  {
    "id": "U4-PROJECT-P08",
    "section": "PROJECT",
    "type": "checker_choice",
    "prompt": "What does the checker listen for?",
    "choices": [
      "clear command words",
      "long grammar explanation",
      "country names"
    ],
    "answer": "clear command words",
    "audio_text": "",
    "kp_tag": "ai_checker",
    "image_ref": "IMG022",
    "repair_note": "The checker focuses on clear command words."
  },
  {
    "id": "U4-PROJECT-P09",
    "section": "PROJECT",
    "type": "checker_choice",
    "prompt": "What does the checker compare?",
    "choices": [
      "spoken command and action card",
      "animal and color",
      "country and family"
    ],
    "answer": "spoken command and action card",
    "audio_text": "",
    "kp_tag": "ai_checker",
    "image_ref": "IMG022",
    "repair_note": "Your action should match your command."
  },
  {
    "id": "U4-PROJECT-P10",
    "section": "PROJECT",
    "type": "repair_choice",
    "prompt": "Your recording has no please. What do you add?",
    "choices": [
      "please after each command",
      "a long story",
      "a country name"
    ],
    "answer": "please after each command",
    "audio_text": "",
    "kp_tag": "repair",
    "image_ref": "IMG022",
    "repair_note": "Add please to make it polite."
  },
  {
    "id": "U4-PROJECT-P11",
    "section": "PROJECT",
    "type": "repair_choice",
    "prompt": "Your recording uses I like dogs. What do you do?",
    "choices": [
      "replace it with a Unit 4 command",
      "keep it for Unit 4",
      "add more animal words"
    ],
    "answer": "replace it with a Unit 4 command",
    "audio_text": "",
    "kp_tag": "KP_boundary",
    "image_ref": "IMG022",
    "repair_note": "Animals and likes are not Unit 4."
  },
  {
    "id": "U4-PROJECT-P12",
    "section": "PROJECT",
    "type": "final_self_check",
    "prompt": "Choose the complete final script.",
    "choices": [
      "Hello. I'm ___. Listen, please. Look, please. Stand up, please. Open your book, please. Goodbye.",
      "Hello. I like animals. My sister is ten. My country is big. Goodbye.",
      "This is a full grammar explanation of commands."
    ],
    "answer": "Hello. I'm ___. Listen, please. Look, please. Stand up, please. Open your book, please. Goodbye.",
    "audio_text": "",
    "kp_tag": "project_gate",
    "image_ref": "IMG022",
    "repair_note": "Use review greeting plus four Unit 4 commands."
  }
];
const PAGE_SIZE = 3;
const STORE_KEY = "flw-v2-aew-unit004-progress";
const state = {};
const saved = JSON.parse(localStorage.getItem(STORE_KEY) || "{}");

function sectionItems(sectionId){ return QUESTIONS.filter(q => q.section === sectionId); }
function ensureState(sectionId){
  if(!state[sectionId]){
    state[sectionId] = saved[sectionId] || { page:0, selected:{}, shownAnswers:{}, correct:{}, choiceOrder:{} };
  }
  return state[sectionId];
}
function persist(){
  localStorage.setItem(STORE_KEY, JSON.stringify(state));
  updateOverallProgress();
}
function shuffledChoices(sectionId, q){
  const st = ensureState(sectionId);
  if(!st.choiceOrder[q.id]){
    const arr = [...q.choices];
    for(let i = arr.length - 1; i > 0; i--){
      const j = Math.floor(Math.random() * (i + 1));
      [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    st.choiceOrder[q.id] = arr;
  }
  return st.choiceOrder[q.id];
}
function flwSpeak(text, audioKey){
  if(!text) return;
  const normalized = String(text).replace(/\s+/g, " ").trim();
  const normalizedKey = audioKey ? String(audioKey).replace(/\s+/g, " ").trim() : "";
  const audioMap = window.FLW_AUDIO_MAP || {};
  const src = (normalizedKey && audioMap[normalizedKey]) || audioMap[normalized] || audioMap[String(text)];
  if(src){
    const audio = new Audio(src);
    audio.play().catch(() => fallbackSpeak(normalized));
    return;
  }
  fallbackSpeak(normalized);
}
function fallbackSpeak(text){
  try{
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(String(text).replace(/^[^:]+:\s*/, ""));
    u.lang = "en-GB";
    u.rate = 0.78;
    u.pitch = 1.08;
    window.speechSynthesis.speak(u);
  }catch(e){ console.warn("Speech synthesis is not available.", e); }
}
function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({ "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;" }[m]));
}
function initPractice(sectionId){
  ensureState(sectionId);
  renderPractice(sectionId);
}
function renderPractice(sectionId){
  const items = sectionItems(sectionId);
  const st = ensureState(sectionId);
  const totalPages = Math.max(1, Math.ceil(items.length / PAGE_SIZE));
  st.page = Math.min(Math.max(st.page || 0, 0), totalPages - 1);
  const visible = items.slice(st.page * PAGE_SIZE, st.page * PAGE_SIZE + PAGE_SIZE);
  const root = document.getElementById("practice-" + sectionId);
  if(!root) return;
  const correctCount = items.filter(q => st.correct[q.id]).length;
  const pct = items.length ? Math.round((correctCount / items.length) * 100) : 0;
  root.innerHTML = `
    <div class="practice-header">
      <div><p class="mini-kicker">Auto Check</p><h3>Practice ${escapeHtml(sectionId)}</h3></div>
      <div class="practice-tools">
        <span class="progress-pill">Page ${st.page + 1} / ${totalPages}</span>
        <span class="progress-pill">${correctCount} / ${items.length} correct</span>
        <span class="bar" aria-label="progress"><span style="width:${pct}%"></span></span>
      </div>
    </div>
    <div class="q-list">${visible.map(q => renderQuestion(sectionId, q)).join("")}</div>
    <div class="controls">
      <button class="ctrl secondary" onclick="prevPage('${sectionId}')" ${st.page === 0 ? "disabled" : ""}>Previous</button>
      <button class="ctrl" onclick="checkPage('${sectionId}')">Check page</button>
      <button class="ctrl secondary" onclick="resetPage('${sectionId}')">Reset page</button>
      <button class="ctrl" onclick="nextPage('${sectionId}')" ${st.page === totalPages - 1 ? "disabled" : ""}>Next</button>
    </div>`;
}
function renderQuestion(sectionId, q){
  const st = ensureState(sectionId);
  const selected = st.selected[q.id];
  const shown = st.shownAnswers[q.id];
  const correct = st.correct[q.id];
  const audio = q.audio_text ? `<button class="sound" type="button" aria-label="Audio" data-speak="${escapeHtml(q.audio_text)}" data-audio-key="${escapeHtml(q.id + "::" + q.audio_text)}"><span aria-hidden="true">&#128266;</span><span class="sr-only">Audio</span></button>` : "";
  const choices = shuffledChoices(sectionId, q).map(c => {
    const klass = selected === c ? " selected" : "";
    return `<button class="choice${klass}" type="button" onclick='choose(${JSON.stringify(sectionId)}, ${JSON.stringify(q.id)}, ${JSON.stringify(c)})'>${escapeHtml(c)}</button>`;
  }).join("");
  let fb = "";
  if(correct) fb = `<span class="ok">Correct.</span>`;
  if(shown) fb += `<span class="answer-box">Correct Answer: ${escapeHtml(q.answer)}<br>${escapeHtml(q.repair_note)}</span>`;
  return `<div class="q-card" id="card-${q.id}">
    <div class="q-title">${escapeHtml(q.id)}. ${escapeHtml(q.prompt)} ${audio}</div>
    <div class="q-meta">${escapeHtml(q.type)} | ${escapeHtml(q.kp_tag)}</div>
    <div class="choices">${choices}</div>
    <div class="feedback" id="fb-${q.id}">${fb}</div>
  </div>`;
}
function choose(sectionId, qid, choice){
  const st = ensureState(sectionId);
  st.selected[qid] = choice;
  st.shownAnswers[qid] = false;
  if(st.correct[qid]) delete st.correct[qid];
  persist();
  renderPractice(sectionId);
}
function checkPage(sectionId){
  const st = ensureState(sectionId);
  const visible = sectionItems(sectionId).slice(st.page * PAGE_SIZE, st.page * PAGE_SIZE + PAGE_SIZE);
  visible.forEach(q => {
    const fb = document.getElementById("fb-" + q.id);
    const selected = st.selected[q.id];
    if(!selected){
      if(fb) fb.innerHTML = `<span class="bad">Choose one answer.</span>`;
      return;
    }
    if(selected === q.answer){
      st.correct[q.id] = true;
      if(fb) fb.innerHTML = `<span class="ok">Correct.</span>`;
    }else{
      delete st.correct[q.id];
      if(fb) fb.innerHTML = `<span class="bad">Try again.</span> <button class="ctrl try" onclick="retryQuestion('${sectionId}','${q.id}')">Try again</button> <button class="ctrl answer" onclick="showAnswer('${sectionId}','${q.id}')">Correct Answer</button><div class="answer-box">${escapeHtml(q.repair_note)}</div>`;
    }
  });
  persist();
}
function retryQuestion(sectionId, qid){
  const st = ensureState(sectionId);
  delete st.selected[qid];
  delete st.shownAnswers[qid];
  delete st.correct[qid];
  st.choiceOrder[qid] = null;
  persist();
  renderPractice(sectionId);
}
function showAnswer(sectionId, qid){
  const st = ensureState(sectionId);
  st.shownAnswers[qid] = true;
  persist();
  renderPractice(sectionId);
}
function prevPage(sectionId){ ensureState(sectionId).page--; persist(); renderPractice(sectionId); }
function nextPage(sectionId){ ensureState(sectionId).page++; persist(); renderPractice(sectionId); }
function resetPage(sectionId){
  const st = ensureState(sectionId);
  const visible = sectionItems(sectionId).slice(st.page * PAGE_SIZE, st.page * PAGE_SIZE + PAGE_SIZE);
  visible.forEach(q => {
    delete st.selected[q.id];
    delete st.shownAnswers[q.id];
    delete st.correct[q.id];
    delete st.choiceOrder[q.id];
  });
  persist();
  renderPractice(sectionId);
}
function allSections(){ return ["L1","L2","L3","L4","L5","L6","L7","WATCH","PROJECT"]; }
function updateOverallProgress(){
  const all = QUESTIONS.length;
  let correct = 0;
  allSections().forEach(section => {
    const st = ensureState(section);
    correct += sectionItems(section).filter(q => st.correct[q.id]).length;
  });
  const pct = all ? Math.round(correct / all * 100) : 0;
  const score = document.getElementById("overall-score");
  if(score) score.textContent = `${pct}% complete`;
  const kp = {};
  QUESTIONS.forEach(q => {
    kp[q.kp_tag] = kp[q.kp_tag] || { total:0, correct:0 };
    kp[q.kp_tag].total++;
    const st = ensureState(q.section);
    if(st.correct[q.id]) kp[q.kp_tag].correct++;
  });
  const root = document.getElementById("kp-progress");
  if(root){
    root.innerHTML = Object.entries(kp).map(([key, value]) => {
      const p = Math.round(value.correct / value.total * 100);
      return `<div class="kp-card"><strong>${escapeHtml(key.replaceAll("_", " "))}</strong><span>${value.correct}/${value.total}</span><div class="bar"><span style="width:${p}%"></span></div></div>`;
    }).join("");
  }
}
window.addEventListener("DOMContentLoaded", () => {
  document.addEventListener("click", event => {
    const button = event.target.closest(".sound[data-speak]");
    if(button) flwSpeak(button.dataset.speak || "", button.dataset.audioKey || "");
  });
  allSections().forEach(initPractice);
  updateOverallProgress();
});

