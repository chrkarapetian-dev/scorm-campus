<?php

namespace App\Services;


/**
 * Rule-based answers and grading used when no model is available. These are
 * prewritten demo responses based on keyword matching, not academic judgement.
 */
class LocalCourseTutor
{
    public function evaluate(string $answer): array
    {
        $s = mb_strtolower($answer);
        $length = mb_strlen($s);

        $relevant = preg_match('/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|համաթվ|նպատակ|տնտես|շրջակա միջավայր/u', $s);
        $dimensions = count(array_filter([
            preg_match('/սոցիալ|հասարակ|առողջ|կրթ|հավասար/u', $s),
            preg_match('/տնտես|եկամուտ|աշխատ|աղքատ/u', $s),
            preg_match('/բնապահպան|էկոլոգ|շրջակա միջավայր|ռեսուրս|կլիմա/u', $s),
        ]));
        $example = preg_match('/օրինակ|համայնք|քաղաք|գյուղ|ջուր|էներգիա|տրանսպորտ|աղտոտ|թափոն|գործազրկ/u', $s);
        $measurable = preg_match('/ինդիկատոր|ցուցանիշ|համաթիվ|տոկոս|քանակ|չափել|հետևել|տվյալ|մակարդակ|բաժին/u', $s);
        $reasoned = preg_match('/քանի որ|հետևաբար|այսպիսով|որովհետև|կապված|ազդում/u', $s);
        $conceptHits = preg_match_all('/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|նպատակ/u', $s);

        $lengthPoints = match (true) {
            $length >= 450 => 12,
            $length >= 250 => 9,
            $length >= 120 => 6,
            $length >= 40 => 3,
            default => 0,
        };

        $criteria = [
            [
                'id' => 'understanding', 'title' => 'Թեմայի ըմբռնում', 'max' => 30,
                'points' => $relevant ? min(30, 18 + min(12, $conceptHits * 3)) : 0,
                'full' => 'Թեմայի հիմնական գաղափարները ճիշտ են կիրառված։',
                'partial' => 'Թեմային առնչվող միտք կա, բայց հիմնական հասկացությունները կարելի է ավելի հստակ բացատրել։',
                'missing' => 'Բացակայում է կայուն զարգացման թեմայի հստակ բացատրությունը։',
            ],
            [
                'id' => 'connections', 'title' => 'Կայունության չափումների կապ', 'max' => 25,
                'points' => [0, 10, 19, 25][$dimensions],
                'full' => 'Սոցիալական, տնտեսական և բնապահպանական կողմերի կապը հստակ է։',
                'partial' => 'Նշված է չափումներից մի քանիսը, սակայն դրանց կապը լիարժեք բացատրված չէ։',
                'missing' => 'Բացատրեք, թե ինչպես են սոցիալական, տնտեսական կամ բնապահպանական կողմերը կապվում թեմային։',
            ],
            [
                'id' => 'example_measure', 'title' => 'Օրինակ և չափում', 'max' => 25,
                'points' => $example && $measurable ? 25 : ($example || $measurable ? 13 : 0),
                'full' => 'Բերված է համապատասխան օրինակ և դրա առաջընթացը չափելու եղանակ։',
                'partial' => 'Օրինակը կամ չափման եղանակը կա, բայց երկուսն էլ միասին հստակ չեն։',
                'missing' => 'Բերեք կոնկրետ օրինակ և առաջարկեք չափելի տվյալ կամ ինդիկատոր։',
            ],
            [
                'id' => 'completeness', 'title' => 'Ամբողջականություն և հստակություն', 'max' => 20,
                'points' => min(20, $lengthPoints + ($reasoned ? 8 : 0)),
                'full' => 'Պատասխանը ամբողջական է, հստակ և հիմնավորում է գաղափարների կապը։',
                'partial' => 'Պատասխանը հասկանալի է, բայց հիմնավորման կամ ամբողջականության մի մասը պակասում է։',
                'missing' => 'Պատասխանը թերի է կամ դժվար է հասկանալ։',
            ],
        ];

        $breakdown = array_map(function (array $c) {
            $ratio = $c['points'] / $c['max'];

            return [
                'criterion_id' => $c['id'],
                'title_armenian' => $c['title'],
                'points_earned' => $c['points'],
                'max_points' => $c['max'],
                'reasoning_armenian' => $ratio >= .8 ? $c['full'] : ($ratio >= .4 ? $c['partial'] : $c['missing']),
            ];
        }, $criteria);

        $total = array_sum(array_column($breakdown, 'points_earned'));

        return [
            'total_score_awarded' => $total,
            'max_total_score' => 100,
            'grade_level' => match (true) {
                $total >= 90 => 'Օրինակելի',
                $total >= 75 => 'Իմացող / Լիարժեք',
                $total >= 60 => 'Զարգացող',
                default => 'Անբավարար',
            },
            'summary_feedback_armenian' => match (true) {
                $total < 60 => 'Սպասելիքներից ցածր է․ պատասխանը թերի է, շեղված թեմայից կամ անճիշտ, կամ բացակայում են պահանջվող հիմնական տարրերը։',
                $total < 75 => 'Պատասխանը մասամբ է բացահայտում թեման։ Ավելացրեք գաղափարների կապը, օրինակն ու չափելի հիմնավորումը։',
                $total < 90 => 'Պատասխանը լավ է ներկայացնում հիմնական գաղափարները, սակայն որոշ կապեր կամ հիմնավորումներ կարելի է լրացնել։',
                default => 'Պատասխանը ամբողջական ու հիմնավորված է և հստակ կապում է կայուն զարգացումը չափելի առաջընթացի հետ։',
            },
            'criterion_breakdown' => $breakdown,
            'actionable_tips_armenian' => $total < 60
                ? 'Վերանայեք թեմայի հիմնական գաղափարները, պատասխանեք հարցի բոլոր մասերին և բերեք համապատասխան օրինակ ու չափելի տվյալ։'
                : 'Ուժեղացրեք հիմնավորումը՝ բացատրելով, թե առաջարկված տվյալը ինչպես է ցույց տալիս կայուն զարգացման առաջընթացը։',
        ];
    }
}
