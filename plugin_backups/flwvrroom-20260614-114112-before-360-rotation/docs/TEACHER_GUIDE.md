# Teacher Guide

## Purpose

FLW VR Room gives learners a simple situation practice room. The MVP includes five built-in scenario presets for A1 speaking/listening readiness.

## Scenario presets

- At the Cafe
- In the Classroom
- At the Hotel
- At the Airport
- At the Supermarket

Each preset changes the room style, hotspot labels, mission prompt, answer choices, and default knowledge point codes.

Each preset also uses a photographic 360-style panorama background. Learners can pan left and right in the scene, then click the floating object labels.

## Learner task

In each scenario, the learner should:

1. Click the five scene objects.
2. Read the situation prompt.
3. Choose the best English reply.
4. Save the attempt.

## Score

The MVP score is:

- Waiter: 20
- Menu: 15
- Cup: 15
- Table: 15
- Cashier: 15
- Correct mission answer: 20

Total: 100

The default passing grade is 70.

## FLW mapping

Recommended A1 cafe knowledge points:

```text
A1-VOC-FOOD-001
A1-FUNC-ORDER-001
A1-LIS-QUESTION-001
A1-SPK-REPLY-001
```

If a teacher leaves the knowledge point box empty, FLW VR Room shows the default KP codes for the selected scenario.

## Classroom use

Use this as a five-minute practice activity after teaching the scenario vocabulary and target function.

For offline or LAN classrooms, keep all media local and test with the same browser students will use.

## Custom rooms

To create a custom room, open the activity settings and enable **Use custom room content**.

You can override:

- Background image URL
- Mission title
- Mission text
- Quiz question
- Quiz answers
- Hotspots

Custom quiz answer format:

```text
answer text|score
```

Use `*` before the best answer if you do not want to think about the score:

```text
*Yes, please.|20
No, I am a student.|0
The gate is blue.|0
```

Custom hotspot format:

```text
key|label|score|x|y
```

`x` and `y` are percentages across the panorama.

Example:

```text
passport|Passport|15|25|68
ticket|Ticket|15|39|66
staff|Staff|20|68|31
```
