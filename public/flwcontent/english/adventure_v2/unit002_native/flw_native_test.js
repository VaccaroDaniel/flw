/* Source script: flw_audio_config.js */
// FLW audio configuration placeholder.
// This unit uses browser speechSynthesis by default.
// Later, replace flwSpeak(text) in assets/js/practice.js with calls to your online or offline TTS server.
window.FLW_AUDIO_MODE = 'speechSynthesis-placeholder';


/* Source script: practice.js */

const QUESTIONS = [{"id": "Q001", "section": "L1", "type": "image-choice", "prompt": "Choose red.", "choices": ["red", "blue", "yellow"], "answer": "red", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q002", "section": "L1", "type": "image-choice", "prompt": "Choose blue.", "choices": ["green", "blue", "red"], "answer": "blue", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q003", "section": "L1", "type": "image-choice", "prompt": "Choose yellow.", "choices": ["yellow", "red", "blue"], "answer": "yellow", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q004", "section": "L1", "type": "image-choice", "prompt": "Choose green.", "choices": ["blue", "yellow", "green"], "answer": "green", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q005", "section": "L1", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["red", "green", "blue"], "answer": "red", "audio_text": "red", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q006", "section": "L1", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["yellow", "blue", "red"], "answer": "blue", "audio_text": "blue", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q007", "section": "L1", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["yellow", "green", "red"], "answer": "yellow", "audio_text": "yellow", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q008", "section": "L1", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["blue", "green", "yellow"], "answer": "green", "audio_text": "green", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q009", "section": "L1", "type": "match", "prompt": "Match the red card.", "choices": ["red", "blue", "green"], "answer": "red", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q010", "section": "L1", "type": "match", "prompt": "Match the blue card.", "choices": ["yellow", "blue", "red"], "answer": "blue", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q011", "section": "L1", "type": "review-choice", "prompt": "Complete: Hello. I’m ___.", "choices": ["Leo", "red", "blue"], "answer": "Leo", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q012", "section": "L1", "type": "closing-choice", "prompt": "Choose the closing word.", "choices": ["Hello", "Goodbye", "red"], "answer": "Goodbye", "audio_text": "", "image_ref": "IMG002-003", "kp_tag": "colors_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q013", "section": "L2", "type": "image-choice", "prompt": "Choose orange.", "choices": ["orange", "yellow", "red"], "answer": "orange", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q014", "section": "L2", "type": "image-choice", "prompt": "Choose purple.", "choices": ["blue", "purple", "green"], "answer": "purple", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q015", "section": "L2", "type": "image-choice", "prompt": "Choose pink.", "choices": ["pink", "black", "brown"], "answer": "pink", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q016", "section": "L2", "type": "image-choice", "prompt": "Choose black.", "choices": ["white", "black", "blue"], "answer": "black", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q017", "section": "L2", "type": "image-choice", "prompt": "Choose white.", "choices": ["brown", "green", "white"], "answer": "white", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q018", "section": "L2", "type": "image-choice", "prompt": "Choose brown.", "choices": ["brown", "orange", "pink"], "answer": "brown", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q019", "section": "L2", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["orange", "purple", "green"], "answer": "orange", "audio_text": "orange", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q020", "section": "L2", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["pink", "purple", "yellow"], "answer": "purple", "audio_text": "purple", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q021", "section": "L2", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["pink", "white", "red"], "answer": "pink", "audio_text": "pink", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q022", "section": "L2", "type": "match", "prompt": "Match: black card.", "choices": ["black", "blue", "brown"], "answer": "black", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q023", "section": "L2", "type": "match", "prompt": "Match: white card.", "choices": ["yellow", "white", "green"], "answer": "white", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q024", "section": "L2", "type": "match", "prompt": "Match: brown card.", "choices": ["red", "orange", "brown"], "answer": "brown", "audio_text": "", "image_ref": "IMG004-005", "kp_tag": "colors_10", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q025", "section": "L3", "type": "complete", "prompt": "It’s ___.", "choices": ["red", "Leo", "hello"], "answer": "red", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q026", "section": "L3", "type": "complete", "prompt": "It’s ___.", "choices": ["blue", "Mia", "goodbye"], "answer": "blue", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q027", "section": "L3", "type": "complete", "prompt": "It’s ___.", "choices": ["yellow", "Toto", "hello"], "answer": "yellow", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q028", "section": "L3", "type": "complete", "prompt": "It’s ___.", "choices": ["green", "Emma", "goodbye"], "answer": "green", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q029", "section": "L3", "type": "phrase-order", "prompt": "Make the sentence.", "choices": ["It’s red.", "red It’s.", "Hello red."], "answer": "It’s red.", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q030", "section": "L3", "type": "phrase-order", "prompt": "Make the sentence.", "choices": ["It’s blue.", "blue It’s.", "Goodbye blue."], "answer": "It’s blue.", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q031", "section": "L3", "type": "phrase-order", "prompt": "Make the sentence.", "choices": ["It’s yellow.", "yellow It’s.", "Hello yellow."], "answer": "It’s yellow.", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q032", "section": "L3", "type": "phrase-order", "prompt": "Make the sentence.", "choices": ["It’s green.", "green It’s.", "Goodbye green."], "answer": "It’s green.", "audio_text": "", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q033", "section": "L3", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s red.", "It’s blue.", "Goodbye."], "answer": "It’s red.", "audio_text": "It’s red.", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q034", "section": "L3", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s yellow.", "It’s blue.", "Hello."], "answer": "It’s blue.", "audio_text": "It’s blue.", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q035", "section": "L3", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s yellow.", "It’s green.", "I’m Leo."], "answer": "It’s yellow.", "audio_text": "It’s yellow.", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q036", "section": "L3", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s red.", "It’s green.", "Hi."], "answer": "It’s green.", "audio_text": "It’s green.", "image_ref": "IMG006-008", "kp_tag": "its_color", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q037", "section": "L4", "type": "image-choice", "prompt": "Red book. Choose the color.", "choices": ["red", "blue", "yellow"], "answer": "red", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q038", "section": "L4", "type": "image-choice", "prompt": "Blue bag. Choose the color.", "choices": ["green", "blue", "red"], "answer": "blue", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q039", "section": "L4", "type": "image-choice", "prompt": "Yellow pencil. Choose the color.", "choices": ["yellow", "orange", "purple"], "answer": "yellow", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q040", "section": "L4", "type": "image-choice", "prompt": "Green box. Choose the color.", "choices": ["blue", "green", "pink"], "answer": "green", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q041", "section": "L4", "type": "match", "prompt": "red book →", "choices": ["red", "black", "white"], "answer": "red", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q042", "section": "L4", "type": "match", "prompt": "blue bag →", "choices": ["brown", "blue", "yellow"], "answer": "blue", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q043", "section": "L4", "type": "match", "prompt": "yellow pencil →", "choices": ["green", "yellow", "pink"], "answer": "yellow", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q044", "section": "L4", "type": "match", "prompt": "green box →", "choices": ["orange", "red", "green"], "answer": "green", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q045", "section": "L4", "type": "audio-choice", "prompt": "Listen. Choose the object.", "choices": ["red book", "blue bag", "green box"], "answer": "red book", "audio_text": "It’s red.", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q046", "section": "L4", "type": "audio-choice", "prompt": "Listen. Choose the object.", "choices": ["yellow pencil", "blue bag", "red book"], "answer": "blue bag", "audio_text": "It’s blue.", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q047", "section": "L4", "type": "audio-choice", "prompt": "Listen. Choose the object.", "choices": ["green box", "orange box", "pink bag"], "answer": "green box", "audio_text": "It’s green.", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q048", "section": "L4", "type": "complete", "prompt": "It’s ___.", "choices": ["blue", "bag", "book"], "answer": "blue", "audio_text": "", "image_ref": "IMG009-010", "kp_tag": "color_objects", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q049", "section": "L5", "type": "story-choice", "prompt": "Leo finds a clue. What color?", "choices": ["red", "blue", "green"], "answer": "red", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q050", "section": "L5", "type": "story-choice", "prompt": "Mia finds a clue. What color?", "choices": ["yellow", "blue", "red"], "answer": "blue", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q051", "section": "L5", "type": "story-choice", "prompt": "Emma finds a clue. What color?", "choices": ["green", "yellow", "blue"], "answer": "yellow", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q052", "section": "L5", "type": "story-choice", "prompt": "Toto finds a clue. What color?", "choices": ["green", "red", "orange"], "answer": "green", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q053", "section": "L5", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["red", "blue", "yellow"], "answer": "red", "audio_text": "It’s red.", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q054", "section": "L5", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["green", "blue", "red"], "answer": "blue", "audio_text": "It’s blue.", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q055", "section": "L5", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["yellow", "purple", "black"], "answer": "yellow", "audio_text": "It’s yellow.", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q056", "section": "L5", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["orange", "green", "blue"], "answer": "green", "audio_text": "It’s green.", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q057", "section": "L5", "type": "sequence", "prompt": "Story order.", "choices": ["red → blue → yellow → green", "blue → red → green → yellow", "yellow → blue → red → green"], "answer": "red → blue → yellow → green", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q058", "section": "L5", "type": "complete", "prompt": "Leo: It’s ___.", "choices": ["red", "Mia", "Toto"], "answer": "red", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q059", "section": "L5", "type": "complete", "prompt": "Mia: It’s ___.", "choices": ["blue", "Leo", "Emma"], "answer": "blue", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q060", "section": "L5", "type": "closing-choice", "prompt": "End of the hunt. Choose the word.", "choices": ["Goodbye", "yellow", "green"], "answer": "Goodbye", "audio_text": "", "image_ref": "IMG011-013", "kp_tag": "color_clue_hunt", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q061", "section": "L6", "type": "image-choice", "prompt": "Choose brown.", "choices": ["brown", "black", "orange"], "answer": "brown", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q062", "section": "L6", "type": "image-choice", "prompt": "Choose orange.", "choices": ["pink", "orange", "yellow"], "answer": "orange", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q063", "section": "L6", "type": "image-choice", "prompt": "Choose pink.", "choices": ["purple", "pink", "red"], "answer": "pink", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q064", "section": "L6", "type": "complete", "prompt": "It’s ___.", "choices": ["brown", "Toto", "hello"], "answer": "brown", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q065", "section": "L6", "type": "complete", "prompt": "It’s ___.", "choices": ["orange", "Mia", "goodbye"], "answer": "orange", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q066", "section": "L6", "type": "complete", "prompt": "It’s ___.", "choices": ["pink", "Leo", "color"], "answer": "pink", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q067", "section": "L6", "type": "sorting", "prompt": "Red card goes with:", "choices": ["red basket", "blue basket", "green basket"], "answer": "red basket", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q068", "section": "L6", "type": "sorting", "prompt": "Blue card goes with:", "choices": ["yellow basket", "blue basket", "pink basket"], "answer": "blue basket", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q069", "section": "L6", "type": "sorting", "prompt": "White card goes with:", "choices": ["black basket", "brown basket", "white basket"], "answer": "white basket", "audio_text": "", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q070", "section": "L6", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["purple", "orange", "white"], "answer": "purple", "audio_text": "purple", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q071", "section": "L6", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["brown", "black", "green"], "answer": "black", "audio_text": "black", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q072", "section": "L6", "type": "audio-choice", "prompt": "Listen. Choose the color.", "choices": ["brown", "pink", "blue"], "answer": "brown", "audio_text": "brown", "image_ref": "IMG014-016", "kp_tag": "full_color_review", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q073", "section": "L7", "type": "project-choice", "prompt": "Poster name line.", "choices": ["I’m ___.", "Goodbye.", "red"], "answer": "I’m ___.", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q074", "section": "L7", "type": "project-choice", "prompt": "Color word for red card.", "choices": ["red", "Leo", "hello"], "answer": "red", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q075", "section": "L7", "type": "project-choice", "prompt": "Color word for blue card.", "choices": ["blue", "Mia", "goodbye"], "answer": "blue", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q076", "section": "L7", "type": "complete", "prompt": "Hello. I’m ___.", "choices": ["Leo", "red", "blue"], "answer": "Leo", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q077", "section": "L7", "type": "complete", "prompt": "It’s ___.", "choices": ["red", "Emma", "hello"], "answer": "red", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q078", "section": "L7", "type": "complete", "prompt": "It’s ___.", "choices": ["blue", "Toto", "goodbye"], "answer": "blue", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q079", "section": "L7", "type": "phrase-order", "prompt": "Make the project start.", "choices": ["Hello. I’m Leo.", "I’m Hello Leo.", "Leo. Hello."], "answer": "Hello. I’m Leo.", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q080", "section": "L7", "type": "phrase-order", "prompt": "Make the color line.", "choices": ["It’s red.", "red It’s.", "Hello red."], "answer": "It’s red.", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q081", "section": "L7", "type": "phrase-order", "prompt": "Make the color line.", "choices": ["It’s blue.", "blue It’s.", "Goodbye blue."], "answer": "It’s blue.", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q082", "section": "L7", "type": "phrase-order", "prompt": "Make the project end.", "choices": ["Goodbye.", "It’s goodbye.", "Hello."], "answer": "Goodbye.", "audio_text": "", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q083", "section": "L7", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["Hello. I’m Mia.", "It’s green.", "Goodbye."], "answer": "Hello. I’m Mia.", "audio_text": "Hello. I’m Mia.", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q084", "section": "L7", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["Hello.", "Goodbye.", "It’s blue."], "answer": "Goodbye.", "audio_text": "Goodbye.", "image_ref": "IMG017-020", "kp_tag": "project_rehearsal", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q085", "section": "WATCH", "type": "video-choice", "prompt": "First greeting.", "choices": ["Hello!", "Goodbye!", "red"], "answer": "Hello!", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q086", "section": "WATCH", "type": "video-choice", "prompt": "Leo’s clue.", "choices": ["red", "blue", "yellow"], "answer": "red", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q087", "section": "WATCH", "type": "video-choice", "prompt": "Mia’s clue.", "choices": ["green", "blue", "red"], "answer": "blue", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q088", "section": "WATCH", "type": "video-choice", "prompt": "Emma’s clue.", "choices": ["yellow", "orange", "brown"], "answer": "yellow", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q089", "section": "WATCH", "type": "video-choice", "prompt": "Toto’s clue.", "choices": ["green", "pink", "black"], "answer": "green", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q090", "section": "WATCH", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s red.", "It’s blue.", "Goodbye."], "answer": "It’s red.", "audio_text": "It’s red.", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q091", "section": "WATCH", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s green.", "It’s blue.", "Hello."], "answer": "It’s blue.", "audio_text": "It’s blue.", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q092", "section": "WATCH", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s yellow.", "It’s red.", "I’m Leo."], "answer": "It’s yellow.", "audio_text": "It’s yellow.", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q093", "section": "WATCH", "type": "audio-choice", "prompt": "Listen. Choose the sentence.", "choices": ["It’s blue.", "It’s green.", "Goodbye."], "answer": "It’s green.", "audio_text": "It’s green.", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q094", "section": "WATCH", "type": "sequence", "prompt": "Watch color order.", "choices": ["red → blue → yellow → green", "green → yellow → blue → red", "blue → red → green → yellow"], "answer": "red → blue → yellow → green", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q095", "section": "WATCH", "type": "complete", "prompt": "It’s ___.", "choices": ["green", "Toto", "hello"], "answer": "green", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q096", "section": "WATCH", "type": "closing-choice", "prompt": "End of video.", "choices": ["Goodbye!", "red", "blue"], "answer": "Goodbye!", "audio_text": "", "image_ref": "WATCH-001", "kp_tag": "watch_colors", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q097", "section": "PROJECT", "type": "project-choice", "prompt": "Choose the project title.", "choices": ["My Color Mini-Poster", "My Family", "My Age"], "answer": "My Color Mini-Poster", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q098", "section": "PROJECT", "type": "project-choice", "prompt": "Choose the name line.", "choices": ["I’m ___.", "It’s red.", "Goodbye."], "answer": "I’m ___.", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q099", "section": "PROJECT", "type": "color-choice", "prompt": "Choose a color word.", "choices": ["red", "Leo", "Toto"], "answer": "red", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q100", "section": "PROJECT", "type": "color-choice", "prompt": "Choose a color word.", "choices": ["blue", "hello", "Mia"], "answer": "blue", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q101", "section": "PROJECT", "type": "color-choice", "prompt": "Choose a color word.", "choices": ["green", "goodbye", "Emma"], "answer": "green", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q102", "section": "PROJECT", "type": "complete", "prompt": "It’s ___.", "choices": ["red", "Leo", "hello"], "answer": "red", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q103", "section": "PROJECT", "type": "complete", "prompt": "It’s ___.", "choices": ["blue", "Mia", "goodbye"], "answer": "blue", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q104", "section": "PROJECT", "type": "complete", "prompt": "It’s ___.", "choices": ["green", "Toto", "hello"], "answer": "green", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q105", "section": "PROJECT", "type": "phrase-order", "prompt": "Start the recording.", "choices": ["Hello. I’m ___.", "I’m Hello.", "Goodbye. I’m ___."], "answer": "Hello. I’m ___.", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q106", "section": "PROJECT", "type": "phrase-order", "prompt": "Say a color.", "choices": ["It’s red.", "red It’s.", "Hello red."], "answer": "It’s red.", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q107", "section": "PROJECT", "type": "phrase-order", "prompt": "Say another color.", "choices": ["It’s blue.", "blue It’s.", "Goodbye blue."], "answer": "It’s blue.", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}, {"id": "Q108", "section": "PROJECT", "type": "phrase-order", "prompt": "End the recording.", "choices": ["Goodbye.", "Hello.", "It’s goodbye."], "answer": "Goodbye.", "audio_text": "", "image_ref": "IMG018-020", "kp_tag": "project_readiness", "feedback_hint": "Look again. Then choose the color."}];
const PAGE_SIZE = 3;
const state = {};
function flwSpeak(text){
  if(!text) return;
  try {
    window.speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance(text.replace(/^[^:]+:\s*/,''));
    u.lang = 'en-US'; u.rate = 0.78; u.pitch = 1.06;
    window.speechSynthesis.speak(u);
  } catch(e) { console.log('Speech not available', e); }
}
function initPractice(sectionId){
  if(!state[sectionId]) state[sectionId] = {page:0, selected:{}, shownAnswers:{}};
  renderPractice(sectionId);
}
function sectionItems(sectionId){ return QUESTIONS.filter(q=>q.section===sectionId); }
function renderPractice(sectionId){
  const items = sectionItems(sectionId);
  const st = state[sectionId] || (state[sectionId]={page:0, selected:{}, shownAnswers:{}});
  const totalPages = Math.ceil(items.length / PAGE_SIZE);
  if(st.page < 0) st.page = 0;
  if(st.page >= totalPages) st.page = totalPages-1;
  const start = st.page * PAGE_SIZE;
  const visible = items.slice(start, start + PAGE_SIZE);
  const root = document.getElementById('practice-' + sectionId);
  if(!root) return;
  root.innerHTML = `
    <div class="practice-header">
      <h4>Practice</h4>
      <div class="progress-pill">Page ${st.page+1} / ${totalPages} · 3 items</div>
    </div>
    <div class="q-list">${visible.map(q=>renderQuestion(sectionId,q)).join('')}</div>
    <div class="controls">
      <button class="ctrl secondary" onclick="prevPage('${sectionId}')" ${st.page===0?'disabled':''}>Previous</button>
      <button class="ctrl" onclick="checkPage('${sectionId}')">Check Page</button>
      <button class="ctrl secondary" onclick="resetPage('${sectionId}')">Reset Page</button>
      <button class="ctrl" onclick="nextPage('${sectionId}')" ${st.page===totalPages-1?'disabled':''}>Next Page</button>
    </div>`;
}
function renderQuestion(sectionId,q){
  const st = state[sectionId];
  const selected = st.selected[q.id];
  const shown = st.shownAnswers[q.id];
  const audio = q.audio_text ? `<button class="sound" onclick='flwSpeak(${JSON.stringify(q.audio_text)})' title="Listen">🔊</button>` : '';
  const choices = q.choices.map(c => {
    const selectedClass = selected === c ? ' selected' : '';
    return `<button class="choice${selectedClass}" onclick='choose(${JSON.stringify(sectionId)},${JSON.stringify(q.id)},${JSON.stringify(c)})'>${escapeHtml(c)}</button>`;
  }).join('');
  const answer = shown ? `<span class="answer-box">Correct Answer: ${escapeHtml(q.answer)}</span>` : '';
  return `<div class="q-card" id="card-${q.id}">
    <div class="q-title">${q.id} · ${escapeHtml(q.prompt)} ${audio}</div>
    <div class="choices">${choices}</div>
    <div class="feedback" id="fb-${q.id}">${answer}</div>
  </div>`;
}
function choose(sectionId,qid,choice){
  const st = state[sectionId];
  st.selected[qid] = choice;
  st.shownAnswers[qid] = false;
  renderPractice(sectionId);
}
function checkPage(sectionId){
  const st = state[sectionId];
  const items = sectionItems(sectionId).slice(st.page*PAGE_SIZE, st.page*PAGE_SIZE+PAGE_SIZE);
  for(const q of items){
    const fb = document.getElementById('fb-'+q.id);
    const selected = st.selected[q.id];
    if(!selected){ fb.innerHTML = '<span class="bad">Choose one answer.</span>'; continue; }
    if(selected === q.answer){ fb.innerHTML = '<span class="ok">Correct!</span>'; }
    else {
      fb.innerHTML = `<span class="bad">Try again.</span> <button class="ctrl try" onclick="retryQuestion('${sectionId}','${q.id}')">Try again</button> <button class="ctrl answer" onclick="showAnswer('${sectionId}','${q.id}')">Correct Answer</button>`;
    }
  }
}
function retryQuestion(sectionId,qid){
  const st=state[sectionId];
  st.selected[qid]=null;
  st.shownAnswers[qid]=false;
  renderPractice(sectionId);
}
function showAnswer(sectionId,qid){
  const st=state[sectionId];
  st.shownAnswers[qid]=true;
  renderPractice(sectionId);
}
function prevPage(sectionId){ state[sectionId].page--; renderPractice(sectionId); }
function nextPage(sectionId){ state[sectionId].page++; renderPractice(sectionId); }
function resetPage(sectionId){
  const st=state[sectionId];
  const items = sectionItems(sectionId).slice(st.page*PAGE_SIZE, st.page*PAGE_SIZE+PAGE_SIZE);
  for(const q of items){ delete st.selected[q.id]; delete st.shownAnswers[q.id]; }
  renderPractice(sectionId);
}
function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
window.addEventListener('DOMContentLoaded',()=>{ ['L1','L2','L3','L4','L5','L6','L7','WATCH','PROJECT'].forEach(initPractice); });


// v2 compact-plus revision: image enlargement modal
function openImageModal(src, alt){
  const modal = document.getElementById('image-modal');
  const img = document.getElementById('modal-img');
  const cap = document.getElementById('modal-cap');
  if(!modal || !img) return;
  img.src = src;
  img.alt = alt || 'large lesson image';
  if(cap) cap.textContent = alt || '';
  modal.classList.add('open');
  modal.setAttribute('aria-hidden','false');
}
function closeImageModal(event){
  const modal = document.getElementById('image-modal');
  if(!modal) return;
  if(event && event.type === 'click'){
    const target = event.target;
    if(target && target.id !== 'image-modal' && !target.classList.contains('modal-close')) return;
  }
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden','true');
}
window.addEventListener('keydown', e => { if(e.key === 'Escape') closeImageModal(); });


/* Source script: flw_local_audio.js */
﻿(function(){
  const map = {
    "byId":  {
                 "AEW2-U002-AUD-014":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-014.mp3"
                                       ],
                 "AEW2-U002-AUD-041":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-041.mp3"
                                       ],
                 "AEW2-U002-AUD-007":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-007.mp3"
                                       ],
                 "AEW2-U002-AUD-031":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-031.mp3"
                                       ],
                 "AEW2-U002-AUD-054":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-054.mp3"
                                       ],
                 "AEW2-U002-AUD-043":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-043.mp3"
                                       ],
                 "AEW2-U002-AUD-016":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-016.mp3"
                                       ],
                 "AEW2-U002-AUD-010":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-010.mp3"
                                       ],
                 "AEW2-U002-AUD-057":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-057.mp3"
                                       ],
                 "AEW2-U002-AUD-019":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-019.mp3"
                                       ],
                 "AEW2-U002-AUD-046":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-046.mp3"
                                       ],
                 "AEW2-U002-AUD-038":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-038.mp3"
                                       ],
                 "AEW2-U002-AUD-039":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-039.mp3"
                                       ],
                 "AEW2-U002-AUD-029":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-029.mp3"
                                       ],
                 "AEW2-U002-AUD-020":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-020.mp3"
                                       ],
                 "AEW2-U002-AUD-013":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-013.mp3"
                                       ],
                 "AEW2-U002-AUD-051":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-051.mp3"
                                       ],
                 "AEW2-U002-AUD-009":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-009.mp3"
                                       ],
                 "AEW2-U002-AUD-015":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-015.mp3"
                                       ],
                 "AEW2-U002-AUD-034":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-034.mp3"
                                       ],
                 "AEW2-U002-AUD-028":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-028.mp3"
                                       ],
                 "AEW2-U002-AUD-040":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-040.mp3"
                                       ],
                 "AEW2-U002-AUD-025":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-025.mp3"
                                       ],
                 "AEW2-U002-AUD-050":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-050.mp3"
                                       ],
                 "AEW2-U002-AUD-060":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-060.mp3"
                                       ],
                 "AEW2-U002-AUD-030":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-030.mp3"
                                       ],
                 "AEW2-U002-AUD-024":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-024.mp3"
                                       ],
                 "AEW2-U002-AUD-042":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-042.mp3"
                                       ],
                 "AEW2-U002-AUD-003":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-003.mp3"
                                       ],
                 "AEW2-U002-AUD-004":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-004.mp3"
                                       ],
                 "AEW2-U002-AUD-005":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-005.mp3"
                                       ],
                 "AEW2-U002-AUD-017":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-017.mp3"
                                       ],
                 "AEW2-U002-AUD-033":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-033.mp3"
                                       ],
                 "AEW2-U002-AUD-061":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-061.mp3"
                                       ],
                 "AEW2-U002-AUD-008":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-008.mp3"
                                       ],
                 "AEW2-U002-AUD-022":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-022.mp3"
                                       ],
                 "AEW2-U002-AUD-035":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-035.mp3"
                                       ],
                 "AEW2-U002-AUD-027":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-027.mp3"
                                       ],
                 "AEW2-U002-AUD-058":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-058.mp3"
                                       ],
                 "AEW2-U002-AUD-023":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-023.mp3"
                                       ],
                 "AEW2-U002-AUD-032":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-032.mp3"
                                       ],
                 "AEW2-U002-AUD-044":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-044.mp3"
                                       ],
                 "AEW2-U002-AUD-049":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-049.mp3"
                                       ],
                 "AEW2-U002-AUD-062":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-062.mp3"
                                       ],
                 "AEW2-U002-AUD-036":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-036.mp3"
                                       ],
                 "AEW2-U002-AUD-053":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-053.mp3"
                                       ],
                 "AEW2-U002-AUD-048":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-048.mp3"
                                       ],
                 "AEW2-U002-AUD-011":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-011.mp3"
                                       ],
                 "AEW2-U002-AUD-037":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-037.mp3"
                                       ],
                 "AEW2-U002-AUD-063":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-063.mp3"
                                       ],
                 "AEW2-U002-AUD-056":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-056.mp3"
                                       ],
                 "AEW2-U002-AUD-055":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-055.mp3"
                                       ],
                 "AEW2-U002-AUD-002":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-002.mp3"
                                       ],
                 "AEW2-U002-AUD-059":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-059.mp3"
                                       ],
                 "AEW2-U002-AUD-026":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-026.mp3"
                                       ],
                 "AEW2-U002-AUD-021":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-021.mp3"
                                       ],
                 "AEW2-U002-AUD-052":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-052.mp3"
                                       ],
                 "AEW2-U002-AUD-018":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-018.mp3"
                                       ],
                 "AEW2-U002-AUD-047":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-047.mp3"
                                       ],
                 "AEW2-U002-AUD-012":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-012.mp3"
                                       ],
                 "AEW2-U002-AUD-045":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-045.mp3"
                                       ],
                 "AEW2-U002-AUD-001":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-001.mp3"
                                       ],
                 "AEW2-U002-AUD-006":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-006.mp3"
                                       ]
             },
    "byText":  {
                   "hello. i’m ___. it’s red. it’s bl\u0027e. goodbye.":  [
                                                                              "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-021.mp3"
                                                                          ],
                   "I’m Emma.":  [
                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-028.mp3"
                                 ],
                   "bl\u0027e":  [
                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-004.mp3"
                                 ],
                   "It’s yellow.":  [
                                        "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-040.mp3"
                                    ],
                   "Hello!":  [
                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-015.mp3"
                              ],
                   "purple book":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-049.mp3"
                                   ],
                   "Leo: It’s red.":  [
                                          "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-041.mp3"
                                      ],
                   "color mini-poster":  [
                                             "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-008.mp3"
                                         ],
                   "Hello. I’m Mia.":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-024.mp3"
                                       ],
                   "Hello, Emma.":  [
                                        "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-016.mp3"
                                    ],
                   "orange, p\u0027rple, pink":  [
                                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-045.mp3"
                                                 ],
                   "purple":  [
                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-048.mp3"
                              ],
                   "Emma: It’s yellow.":  [
                                              "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-010.mp3"
                                          ],
                   "Hello, Leo.":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-017.mp3"
                                   ],
                   "Toto: It’s green.":  [
                                             "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-057.mp3"
                                         ],
                   "Red, blue.":  [
                                      "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-053.mp3"
                                  ],
                   "blue bag":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-005.mp3"
                                ],
                   "it’s bl\u0027e.":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-042.mp3"
                                       ],
                   "I’m Leo.":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-029.mp3"
                                ],
                   "It’s white.":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-039.mp3"
                                   ],
                   "red, bl\u0027e.":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-053.mp3"
                                       ],
                   "yellow":  [
                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-061.mp3"
                              ],
                   "It’s orange.":  [
                                        "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-035.mp3"
                                    ],
                   "It’s pink.":  [
                                      "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-036.mp3"
                                  ],
                   "mia: it’s bl\u0027e.":  [
                                                "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-042.mp3"
                                            ],
                   "short voice greeting":  [
                                                "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-055.mp3"
                                            ],
                   "hello. i’m emma. it’s pink. it’s p\u0027rple. goodbye.":  [
                                                                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-022.mp3"
                                                                              ],
                   "brown box":  [
                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-007.mp3"
                                 ],
                   "retry recording if needed":  [
                                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-054.mp3"
                                                 ],
                   "Hello. I’m ___. It’s red. It’s blue. Goodbye.":  [
                                                                         "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-021.mp3"
                                                                     ],
                   "Goodbye.":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-012.mp3"
                                ],
                   "Mia: It’s blue.":  [
                                           "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-042.mp3"
                                       ],
                   "It’s green.":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-057.mp3"
                                   ],
                   "green box":  [
                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-014.mp3"
                                 ],
                   "orange box":  [
                                      "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-044.mp3"
                                  ],
                   "brown":  [
                                 "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-006.mp3"
                             ],
                   "It’s brown.":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-033.mp3"
                                   ],
                   "red":  [
                               "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-050.mp3"
                           ],
                   "Hello.":  [
                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-020.mp3"
                              ],
                   "Goodbye!":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-011.mp3"
                                ],
                   "black, white, brown":  [
                                               "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-003.mp3"
                                           ],
                   "Yellow, green.":  [
                                          "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-063.mp3"
                                      ],
                   "bl\u0027e bag":  [
                                         "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-005.mp3"
                                     ],
                   "Hello, Toto.":  [
                                        "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-019.mp3"
                                    ],
                   "pink bag":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-047.mp3"
                                ],
                   "Hello, Mia.":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-018.mp3"
                                   ],
                   "it’s p\u0027rple.":  [
                                             "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-037.mp3"
                                         ],
                   "What color?":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-058.mp3"
                                   ],
                   "color q\u0027est!":  [
                                             "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-009.mp3"
                                         ],
                   "black":  [
                                 "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-001.mp3"
                             ],
                   "orange":  [
                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-043.mp3"
                              ],
                   "yellow pencil":  [
                                         "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-062.mp3"
                                     ],
                   "Color Quest!":  [
                                        "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-009.mp3"
                                    ],
                   "orange, purple, pink":  [
                                                "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-045.mp3"
                                            ],
                   "red book":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-051.mp3"
                                ],
                   "p\u0027rple":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-048.mp3"
                                   ],
                   "blue":  [
                                "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-004.mp3"
                            ],
                   "It’s blue.":  [
                                      "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-042.mp3"
                                  ],
                   "Hello. I’m Emma. It’s pink. It’s purple. Goodbye.":  [
                                                                             "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-022.mp3"
                                                                         ],
                   "red, bl\u0027e, yellow, green":  [
                                                         "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-052.mp3"
                                                     ],
                   "Hello. I’m Leo. It’s red. It’s blue. Goodbye.":  [
                                                                         "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-023.mp3"
                                                                     ],
                   "It’s purple.":  [
                                        "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-037.mp3"
                                    ],
                   "hello. i’m leo. it’s red. it’s bl\u0027e. goodbye.":  [
                                                                              "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-023.mp3"
                                                                          ],
                   "red, blue, yellow, green":  [
                                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-052.mp3"
                                                ],
                   "I’m ___.":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-027.mp3"
                                ],
                   "teacher or AI feedback":  [
                                                  "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-056.mp3"
                                              ],
                   "It’s red.":  [
                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-041.mp3"
                                 ],
                   "I’m Mia.":  [
                                    "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-030.mp3"
                                ],
                   "white box":  [
                                     "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-060.mp3"
                                 ],
                   "Hello. I’m Mia. It’s yellow. It’s green. Goodbye.":  [
                                                                             "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-025.mp3"
                                                                         ],
                   "p\u0027rple book":  [
                                            "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-049.mp3"
                                        ],
                   "white":  [
                                 "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-059.mp3"
                             ],
                   "pink":  [
                                "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-046.mp3"
                            ],
                   "It’s black.":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-031.mp3"
                                   ],
                   "green":  [
                                 "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-013.mp3"
                             ],
                   "black chair":  [
                                       "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-002.mp3"
                                   ],
                   "Hi.":  [
                               "https://192.168.129.79/flwcontent/english/adventure_v2/unit002_native/assets/audio/AEW2-U002-AUD-026.mp3"
                           ]
               }
};
  function norm(s){
    return String(s || "").replace(/[‘’]/g, "'").replace(/\s+/g, " ").trim().toLowerCase();
  }
  function sourcesFor(button){
    const byId = map.byId || {};
    const byText = map.byText || {};
    const id = button.getAttribute("data-audio-id") || button.dataset.audioId || "";
    if (id && byId[id]) return byId[id];
    let text = button.getAttribute("data-say") || button.getAttribute("data-tts-text") || button.getAttribute("data-audio-text") || "";
    if (!text) {
      const raw = button.getAttribute("onclick") || "";
      const m = raw.match(/flwSpeak\((["'])([\s\S]*?)\1\)/);
      if (m) text = m[2].replace(/\\'/g, "'").replace(/\\"/g, '"');
    }
    text = String(text || "").replace(/\s+/g, " ").trim();
    return byText[text] || byText[norm(text)] || null;
  }
  function playSources(sources, button){
    const list = Array.isArray(sources) ? sources.slice() : [sources];
    if (!list.length) return false;
    if (window.FLW_CURRENT_AUDIO) {
      try { window.FLW_CURRENT_AUDIO.pause(); } catch(e) {}
    }
    let index = 0;
    if (button) button.classList.add("is-playing");
    const next = () => {
      if (index >= list.length) {
        if (button) button.classList.remove("is-playing");
        return;
      }
      const audio = new Audio(list[index++]);
      window.FLW_CURRENT_AUDIO = audio;
      audio.addEventListener("ended", next, { once: true });
      audio.addEventListener("error", next, { once: true });
      audio.play().catch(next);
    };
    next();
    return true;
  }
  window.FLW_PLAY_LOCAL_AUDIO = function(ref, button){
    const byId = map.byId || {};
    const byText = map.byText || {};
    const sources = byId[ref] || byText[ref] || byText[norm(ref)];
    return sources ? playSources(sources, button || null) : false;
  };
  document.addEventListener("click", function(event){
    const button = event.target.closest(".audio-btn,.sound");
    if (!button) return;
    const sources = sourcesFor(button);
    if (!sources) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    playSources(sources, button);
  }, true);
})();

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
(() => {
  const hiddenLabelCmIds = [540,541,542,543,544,545,546,547,548,549,550,551,552];
  const tidyCourseIndex = () => {
    hiddenLabelCmIds.forEach((cmid) => {
      const item = document.getElementById(`course-index-cm-${cmid}`);
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