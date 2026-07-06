(function () {
  "use strict";

  const levels = ["Pre-A1", "A1", "A2", "B1", "B2", "C1"];

  const coreQuestions = [
    { id: "prea1-g01", text: "Choose the correct sentence.", options: ["I am Maria.", "I are Maria.", "I be Maria.", "I is Maria."], answer: "I am Maria.", cefr: "Pre-A1", skill: "grammar", difficulty: 0.12 },
    { id: "prea1-g02", text: "Complete: This ___ my book.", options: ["is", "are", "am", "be"], answer: "is", cefr: "Pre-A1", skill: "grammar", difficulty: 0.13 },
    { id: "prea1-g03", text: "Choose the plural form of 'bag'.", options: ["bags", "bages", "bagies", "bag"], answer: "bags", cefr: "Pre-A1", skill: "grammar", difficulty: 0.14 },
    { id: "prea1-v01", text: "Which word is a color?", options: ["blue", "table", "run", "seven"], answer: "blue", cefr: "Pre-A1", skill: "vocabulary", difficulty: 0.1 },
    { id: "prea1-v02", text: "Which word means a person in your family?", options: ["mother", "window", "pencil", "city"], answer: "mother", cefr: "Pre-A1", skill: "vocabulary", difficulty: 0.12 },
    { id: "prea1-v03", text: "Choose the number.", options: ["three", "green", "school", "small"], answer: "three", cefr: "Pre-A1", skill: "vocabulary", difficulty: 0.11 },
    { id: "prea1-r01", text: "Read: 'Tom has a red pen.' What color is the pen?", options: ["red", "blue", "black", "green"], answer: "red", cefr: "Pre-A1", skill: "reading", difficulty: 0.15 },
    { id: "prea1-r02", text: "Read: 'Anna is at school.' Where is Anna?", options: ["at school", "at home", "in a shop", "on a bus"], answer: "at school", cefr: "Pre-A1", skill: "reading", difficulty: 0.16 },
    { id: "prea1-l01", text: "Listening: You hear 'Good morning.' Choose the best reply.", options: ["Good morning.", "I am twelve.", "It is blue.", "Two books."], answer: "Good morning.", cefr: "Pre-A1", skill: "listening", difficulty: 0.16, audio: "audio/placeholders/prea1-hello.mp3" },
    { id: "prea1-l02", text: "Listening: You hear 'Open your book.' What should you open?", options: ["your book", "the door", "a window", "your bag"], answer: "your book", cefr: "Pre-A1", skill: "listening", difficulty: 0.18, audio: "audio/placeholders/prea1-classroom.mp3" },

    { id: "a1-g01", text: "Complete: She ___ coffee every morning.", options: ["drinks", "drink", "drinking", "is drink"], answer: "drinks", cefr: "A1", skill: "grammar", difficulty: 0.22 },
    { id: "a1-g02", text: "Choose the correct question.", options: ["Where do you live?", "Where you live?", "Where does you live?", "Where are live?"], answer: "Where do you live?", cefr: "A1", skill: "grammar", difficulty: 0.24 },
    { id: "a1-g03", text: "Complete: There ___ two chairs in the room.", options: ["are", "is", "am", "be"], answer: "are", cefr: "A1", skill: "grammar", difficulty: 0.23 },
    { id: "a1-v01", text: "Which word is food?", options: ["rice", "train", "jacket", "street"], answer: "rice", cefr: "A1", skill: "vocabulary", difficulty: 0.2 },
    { id: "a1-v02", text: "A 'doctor' works in a ___.", options: ["hospital", "library", "bank", "station"], answer: "hospital", cefr: "A1", skill: "vocabulary", difficulty: 0.24 },
    { id: "a1-v03", text: "Choose the opposite of 'hot'.", options: ["cold", "late", "big", "fast"], answer: "cold", cefr: "A1", skill: "vocabulary", difficulty: 0.21 },
    { id: "a1-r01", text: "Read: 'The train leaves at six.' When does it leave?", options: ["at six", "at seven", "in the morning", "tomorrow"], answer: "at six", cefr: "A1", skill: "reading", difficulty: 0.26 },
    { id: "a1-r02", text: "Read: 'Mina wants a ticket to Beijing.' What does Mina want?", options: ["a ticket", "a hotel", "a meal", "a map"], answer: "a ticket", cefr: "A1", skill: "reading", difficulty: 0.28 },
    { id: "a1-l01", text: "Listening: You hear a price: 'ten dollars.' Choose the price.", options: ["$10", "$2", "$12", "$20"], answer: "$10", cefr: "A1", skill: "listening", difficulty: 0.25, audio: "audio/placeholders/a1-price.mp3" },
    { id: "a1-l02", text: "Listening: You hear 'Turn left at the bank.' Where do you turn?", options: ["at the bank", "at the school", "at the cafe", "at the station"], answer: "at the bank", cefr: "A1", skill: "listening", difficulty: 0.29, audio: "audio/placeholders/a1-directions.mp3" },

    { id: "a2-g01", text: "Complete: I ___ to the cinema last night.", options: ["went", "go", "gone", "am going"], answer: "went", cefr: "A2", skill: "grammar", difficulty: 0.38 },
    { id: "a2-g02", text: "Choose the correct sentence.", options: ["She is taller than me.", "She is more tall than me.", "She taller than me.", "She is tallest than me."], answer: "She is taller than me.", cefr: "A2", skill: "grammar", difficulty: 0.42 },
    { id: "a2-g03", text: "Complete: We have lived here ___ 2020.", options: ["since", "for", "during", "from"], answer: "since", cefr: "A2", skill: "grammar", difficulty: 0.45 },
    { id: "a2-v01", text: "Which phrase means 'reserve a room'?", options: ["book a room", "take a room", "hold a room", "make a room"], answer: "book a room", cefr: "A2", skill: "vocabulary", difficulty: 0.36 },
    { id: "a2-v02", text: "Choose the word for a short trip for pleasure.", options: ["excursion", "invoice", "appointment", "receipt"], answer: "excursion", cefr: "A2", skill: "vocabulary", difficulty: 0.43 },
    { id: "a2-v03", text: "A person who serves food in a restaurant is a ___.", options: ["waiter", "pilot", "mechanic", "designer"], answer: "waiter", cefr: "A2", skill: "vocabulary", difficulty: 0.35 },
    { id: "a2-r01", text: "Read: 'The museum is closed on Mondays except public holidays.' When is it usually closed?", options: ["Mondays", "Saturdays", "public holidays", "every day"], answer: "Mondays", cefr: "A2", skill: "reading", difficulty: 0.44 },
    { id: "a2-r02", text: "Read: 'Please arrive 15 minutes before departure.' What should passengers do?", options: ["arrive early", "leave late", "buy online", "change seats"], answer: "arrive early", cefr: "A2", skill: "reading", difficulty: 0.4 },
    { id: "a2-l01", text: "Listening: A guest asks for a room with a sea view. What does the guest want?", options: ["a sea view", "breakfast only", "a city tour", "late checkout"], answer: "a sea view", cefr: "A2", skill: "listening", difficulty: 0.41, audio: "audio/placeholders/a2-hotel.mp3" },
    { id: "a2-l02", text: "Listening: A speaker says the meeting was moved to Friday. When is the meeting?", options: ["Friday", "Monday", "Wednesday", "Sunday"], answer: "Friday", cefr: "A2", skill: "listening", difficulty: 0.46, audio: "audio/placeholders/a2-meeting.mp3" },

    { id: "b1-g01", text: "Complete: If I ___ enough time, I would learn another language.", options: ["had", "have", "will have", "having"], answer: "had", cefr: "B1", skill: "grammar", difficulty: 0.56 },
    { id: "b1-g02", text: "Choose the best form: The report ___ by Anna yesterday.", options: ["was written", "wrote", "has written", "was writing"], answer: "was written", cefr: "B1", skill: "grammar", difficulty: 0.58 },
    { id: "b1-g03", text: "Complete: He asked me where I ___.", options: ["lived", "live", "am living", "will live"], answer: "lived", cefr: "B1", skill: "grammar", difficulty: 0.6 },
    { id: "b1-v01", text: "Choose the closest meaning of 'reliable'.", options: ["dependable", "expensive", "temporary", "confused"], answer: "dependable", cefr: "B1", skill: "vocabulary", difficulty: 0.54 },
    { id: "b1-v02", text: "Which word fits: The company plans to ___ its services next year.", options: ["expand", "borrow", "avoid", "repair"], answer: "expand", cefr: "B1", skill: "vocabulary", difficulty: 0.57 },
    { id: "b1-v03", text: "Choose the noun related to 'decide'.", options: ["decision", "decisive", "deciding", "decided"], answer: "decision", cefr: "B1", skill: "vocabulary", difficulty: 0.55 },
    { id: "b1-r01", text: "Read: 'Although the app is free, some advanced lessons require payment.' What is true?", options: ["Some lessons cost money.", "All lessons are free.", "The app cannot be used.", "Payment is required first."], answer: "Some lessons cost money.", cefr: "B1", skill: "reading", difficulty: 0.59 },
    { id: "b1-r02", text: "Read: 'Participants who register before Friday receive a discount.' Who gets a discount?", options: ["early registrants", "all participants", "late registrants", "staff only"], answer: "early registrants", cefr: "B1", skill: "reading", difficulty: 0.61 },
    { id: "b1-l01", text: "Listening: A speaker says the first plan was cancelled because of rain. Why was it cancelled?", options: ["because of rain", "because of cost", "because of traffic", "because of illness"], answer: "because of rain", cefr: "B1", skill: "listening", difficulty: 0.57, audio: "audio/placeholders/b1-plan.mp3" },
    { id: "b1-l02", text: "Listening: A customer accepts the second appointment option. Which option is accepted?", options: ["the second option", "the first option", "no option", "the last week"], answer: "the second option", cefr: "B1", skill: "listening", difficulty: 0.62, audio: "audio/placeholders/b1-appointment.mp3" },

    { id: "b2-g01", text: "Complete: Had I known about the delay, I ___ earlier.", options: ["would have left", "will leave", "would leave", "left"], answer: "would have left", cefr: "B2", skill: "grammar", difficulty: 0.72 },
    { id: "b2-g02", text: "Choose the correct reduced clause.", options: ["Concerned by the results, the team revised the plan.", "Concerning by the results, the team revised the plan.", "The team concerned by revising the plan.", "Concerned the results, revised the team plan."], answer: "Concerned by the results, the team revised the plan.", cefr: "B2", skill: "grammar", difficulty: 0.75 },
    { id: "b2-g03", text: "Complete: The policy is expected ___ next month.", options: ["to be implemented", "implementing", "implemented", "to implement"], answer: "to be implemented", cefr: "B2", skill: "grammar", difficulty: 0.74 },
    { id: "b2-v01", text: "Choose the closest meaning of 'substantial'.", options: ["considerable", "minor", "uncertain", "ordinary"], answer: "considerable", cefr: "B2", skill: "vocabulary", difficulty: 0.7 },
    { id: "b2-v02", text: "Which phrase best fits: The evidence ___ the original claim.", options: ["undermines", "decorates", "translates", "measures"], answer: "undermines", cefr: "B2", skill: "vocabulary", difficulty: 0.73 },
    { id: "b2-v03", text: "Choose the best collocation.", options: ["pose a challenge", "make a challenge", "do a challenge", "hold a challenge"], answer: "pose a challenge", cefr: "B2", skill: "vocabulary", difficulty: 0.69 },
    { id: "b2-r01", text: "Read: 'The author concedes that remote work improves flexibility, yet argues it may weaken informal mentoring.' What is the author's position?", options: ["balanced but cautious", "completely negative", "only enthusiastic", "unrelated to work"], answer: "balanced but cautious", cefr: "B2", skill: "reading", difficulty: 0.76 },
    { id: "b2-r02", text: "Read: 'The proposal is feasible provided that funding is secured.' What condition is necessary?", options: ["funding must be secured", "staff must be replaced", "the proposal must be rejected", "no condition is stated"], answer: "funding must be secured", cefr: "B2", skill: "reading", difficulty: 0.71 },
    { id: "b2-l01", text: "Listening: A lecturer contrasts short-term savings with long-term maintenance costs. What contrast is made?", options: ["initial savings versus later costs", "staff numbers versus salaries", "old users versus new users", "speed versus distance"], answer: "initial savings versus later costs", cefr: "B2", skill: "listening", difficulty: 0.74, audio: "audio/placeholders/b2-lecture.mp3" },
    { id: "b2-l02", text: "Listening: A manager says the launch is viable, assuming legal review ends this week. What must happen?", options: ["legal review must finish", "the launch must be cancelled", "prices must be lowered", "users must be surveyed"], answer: "legal review must finish", cefr: "B2", skill: "listening", difficulty: 0.77, audio: "audio/placeholders/b2-launch.mp3" },

    { id: "c1-g01", text: "Complete: Rarely ___ such a clear explanation of the issue.", options: ["have I heard", "I have heard", "I heard", "have heard I"], answer: "have I heard", cefr: "C1", skill: "grammar", difficulty: 0.86 },
    { id: "c1-g02", text: "Choose the most natural sentence.", options: ["The findings, though preliminary, warrant further investigation.", "The findings although preliminary warrant to investigate more.", "Preliminary though findings warrant investigation further.", "The findings warrant further investigating though preliminary."], answer: "The findings, though preliminary, warrant further investigation.", cefr: "C1", skill: "grammar", difficulty: 0.88 },
    { id: "c1-g03", text: "Complete: The committee insisted that the data ___ independently verified.", options: ["be", "is", "was", "has been"], answer: "be", cefr: "C1", skill: "grammar", difficulty: 0.9 },
    { id: "c1-v01", text: "Choose the closest meaning of 'nuanced'.", options: ["showing subtle differences", "clearly false", "strongly emotional", "recently invented"], answer: "showing subtle differences", cefr: "C1", skill: "vocabulary", difficulty: 0.84 },
    { id: "c1-v02", text: "Which word best completes: The report offers a ___ critique of the policy.", options: ["thorough", "wide", "tall", "loud"], answer: "thorough", cefr: "C1", skill: "vocabulary", difficulty: 0.83 },
    { id: "c1-v03", text: "Choose the closest meaning of 'to mitigate'.", options: ["to reduce the severity of", "to copy exactly", "to announce publicly", "to postpone forever"], answer: "to reduce the severity of", cefr: "C1", skill: "vocabulary", difficulty: 0.85 },
    { id: "c1-r01", text: "Read: 'The article's apparent neutrality masks a sustained preference for market-led reforms.' What does the sentence imply?", options: ["The article is biased beneath neutral language.", "The article rejects all reforms.", "The article has no clear topic.", "The market is not mentioned."], answer: "The article is biased beneath neutral language.", cefr: "C1", skill: "reading", difficulty: 0.9 },
    { id: "c1-r02", text: "Read: 'The hypothesis remains compelling, not because the evidence is conclusive, but because rival explanations are weaker.' Why is it compelling?", options: ["Alternatives are less convincing.", "Evidence is final.", "It is popular.", "It is simple."], answer: "Alternatives are less convincing.", cefr: "C1", skill: "reading", difficulty: 0.89 },
    { id: "c1-l01", text: "Listening: A speaker qualifies her support by noting unresolved ethical concerns. What is her stance?", options: ["supportive with reservations", "strongly opposed", "unaware of the issue", "purely technical"], answer: "supportive with reservations", cefr: "C1", skill: "listening", difficulty: 0.87, audio: "audio/placeholders/c1-panel.mp3" },
    { id: "c1-l02", text: "Listening: A panelist implies the deadline is politically convenient rather than operationally realistic. What is implied?", options: ["The deadline serves politics more than operations.", "The deadline is easy to meet.", "Operations are already complete.", "Politics is irrelevant."], answer: "The deadline serves politics more than operations.", cefr: "C1", skill: "listening", difficulty: 0.91, audio: "audio/placeholders/c1-deadline.mp3" }
  ];

  const diagnosticReading = [
    { id: "diag-r01", text: "A sign says: 'Staff only beyond this point.' Who may enter?", options: ["staff", "visitors", "children", "new students"], answer: "staff", cefr: "A1", skill: "reading", difficulty: 0.25 },
    { id: "diag-r02", text: "Read: 'Lunch is served until 2 p.m.' What happens after 2 p.m.?", options: ["Lunch service ends.", "Lunch starts.", "Dinner is free.", "The cafe opens."], answer: "Lunch service ends.", cefr: "A2", skill: "reading", difficulty: 0.38 },
    { id: "diag-r03", text: "Read: 'Despite poor weather, the event attracted a large crowd.' What happened?", options: ["Many people came.", "The event was cancelled.", "The weather improved.", "Nobody came."], answer: "Many people came.", cefr: "B1", skill: "reading", difficulty: 0.55 },
    { id: "diag-r04", text: "Read: 'Applications submitted after the deadline will not be considered.' What is important?", options: ["Apply on time.", "Apply by phone.", "Apply twice.", "Pay first."], answer: "Apply on time.", cefr: "B1", skill: "reading", difficulty: 0.58 },
    { id: "diag-r05", text: "Read: 'The study questions the assumption that more homework automatically improves outcomes.' What does the study question?", options: ["a common belief about homework", "the existence of schools", "all learning outcomes", "teacher training"], answer: "a common belief about homework", cefr: "B2", skill: "reading", difficulty: 0.72 },
    { id: "diag-r06", text: "Read: 'The new rule is unlikely to be popular, but it may prove necessary.' What is suggested?", options: ["It may be needed despite opposition.", "It is already popular.", "It is unnecessary.", "It has failed."], answer: "It may be needed despite opposition.", cefr: "B2", skill: "reading", difficulty: 0.74 },
    { id: "diag-r07", text: "Read: 'The author's irony lies in praising efficiency while describing its human cost.' What technique is used?", options: ["irony", "definition", "chronology", "quotation only"], answer: "irony", cefr: "C1", skill: "reading", difficulty: 0.84 },
    { id: "diag-r08", text: "Read: 'The conclusion is persuasive insofar as it acknowledges its own limitations.' Why is it persuasive?", options: ["It admits limits.", "It avoids evidence.", "It is very short.", "It blames readers."], answer: "It admits limits.", cefr: "C1", skill: "reading", difficulty: 0.86 },
    { id: "diag-r09", text: "Read: 'The bus stop is opposite the library.' Where is the bus stop?", options: ["across from the library", "inside the library", "behind the cafe", "near the airport"], answer: "across from the library", cefr: "A2", skill: "reading", difficulty: 0.34 },
    { id: "diag-r10", text: "Read: 'No refunds are available unless the class is cancelled.' When can students get a refund?", options: ["if the class is cancelled", "whenever they ask", "after every lesson", "before registration"], answer: "if the class is cancelled", cefr: "B1", skill: "reading", difficulty: 0.6 }
  ];

  const diagnosticListening = [
    { id: "diag-l01", text: "Listening: The speaker says the class starts at nine. When does it start?", options: ["9:00", "10:00", "8:30", "12:00"], answer: "9:00", cefr: "A1", skill: "listening", difficulty: 0.24, audio: "audio/placeholders/diag-class-time.mp3" },
    { id: "diag-l02", text: "Listening: The passenger asks for platform four. What is needed?", options: ["platform four", "gate three", "seat five", "room two"], answer: "platform four", cefr: "A2", skill: "listening", difficulty: 0.36, audio: "audio/placeholders/diag-platform.mp3" },
    { id: "diag-l03", text: "Listening: The speaker says the hotel is convenient but noisy. What is the problem?", options: ["noise", "location", "price", "breakfast"], answer: "noise", cefr: "B1", skill: "listening", difficulty: 0.56, audio: "audio/placeholders/diag-hotel.mp3" },
    { id: "diag-l04", text: "Listening: The guest prefers a refund rather than another booking. What does the guest want?", options: ["a refund", "a later booking", "a bigger room", "a tour"], answer: "a refund", cefr: "B1", skill: "listening", difficulty: 0.59, audio: "audio/placeholders/diag-refund.mp3" },
    { id: "diag-l05", text: "Listening: The speaker says the evidence is promising but incomplete. How strong is the evidence?", options: ["promising but incomplete", "complete and final", "irrelevant", "impossible to understand"], answer: "promising but incomplete", cefr: "B2", skill: "listening", difficulty: 0.73, audio: "audio/placeholders/diag-evidence.mp3" },
    { id: "diag-l06", text: "Listening: A presenter recommends delaying the rollout until training is finished. What is recommended?", options: ["delay the rollout", "cancel training", "launch immediately", "change the product name"], answer: "delay the rollout", cefr: "B2", skill: "listening", difficulty: 0.76, audio: "audio/placeholders/diag-rollout.mp3" },
    { id: "diag-l07", text: "Listening: The interviewee implies that the reform solves one problem while creating another. What is implied?", options: ["It has mixed effects.", "It solves everything.", "It changes nothing.", "It is illegal."], answer: "It has mixed effects.", cefr: "C1", skill: "listening", difficulty: 0.86, audio: "audio/placeholders/diag-reform.mp3" },
    { id: "diag-l08", text: "Listening: The speaker's agreement is tentative rather than wholehearted. What is the speaker's attitude?", options: ["cautious agreement", "complete rejection", "anger", "confusion about the topic"], answer: "cautious agreement", cefr: "C1", skill: "listening", difficulty: 0.88, audio: "audio/placeholders/diag-agreement.mp3" }
  ];

  const profileQuestions = [
    { id: "profile-confidence-speaking", text: "I can introduce myself and answer simple personal questions." },
    { id: "profile-confidence-listening", text: "I can understand the main point when people speak clearly." },
    { id: "profile-confidence-reading", text: "I can read short notices, messages, and simple articles." },
    { id: "profile-confidence-grammar", text: "I can choose accurate grammar when speaking or writing." },
    { id: "profile-confidence-vocabulary", text: "I know enough words for daily study, travel, and work topics." },
    { id: "profile-confidence-writing", text: "I can write a short organized paragraph." },
    { id: "profile-study-habit", text: "I can study regularly without teacher reminders." },
    { id: "profile-test-confidence", text: "My answers in this test show my real level." }
  ];

  window.FLWPlacementQuestionBank = {
    levels,
    coreQuestions,
    diagnosticReading,
    diagnosticListening,
    profileQuestions,
    writingPrompt: {
      id: "writing-001",
      cefr: "mixed",
      skill: "writing",
      minWords: 40,
      maxWords: 80,
      text: "Write 40-80 words about your learning goal and one challenge you want to improve."
    },
    speakingPrompt: {
      id: "speaking-optional-001",
      text: "Optional speaking prompt: Talk for one minute about a place you know well and explain why it is important to you."
    }
  };
})();
