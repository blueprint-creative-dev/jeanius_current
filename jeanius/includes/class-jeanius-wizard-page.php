<?php
/**
 * Front-end “wizard” page that drives the 15-minute timeline flow.
 */
namespace Jeanius;

class Wizard_Page {

	/**
	 * Outputs a bare-bones HTML page (bypasses theme templates).
	 * URL carries ?post={assessment_ID}
	 */
	public static function render() {

		$post_id = \Jeanius\current_assessment_id();
		if ( ! $post_id ) wp_die( 'Please log in first.' );

		$rest_nonce = wp_create_nonce( 'wp_rest' );
		?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php wp_head(); ?>
    <style>
    body {
        font-family: sans-serif;
        padding: 40px;
    }

    #timer {
        font-size: 2rem;
        margin-bottom: 20px;
    }

    textarea {
        width: 100%;
        max-width: 600px;
    }
    </style>
</head>

<body <?php body_class(); ?>>

    <h2>Jeanius Assessment – Timeline Wizard</h2>
    <div id="timer">5:00</div>

    <h3>Early Childhood (0 – 5)</h3>
    <p>Enter 2-3 words, people, events, or experiences that describe this stage.<br>
        Put each on its own line.</p>

    <textarea id="entries" rows="4" placeholder="One idea per line"></textarea><br><br>

    <button id="save-btn" class="button button-primary" disabled>Save &amp; Continue</button>

    <script>
    /* ---------- 15-minute countdown ---------- */
    let secs = 5 * 60;
    const elTimer = document.getElementById('timer');
    (function tick() {
        const m = String(Math.floor(secs / 60)).padStart(2, '0');
        const s = String(secs % 60).padStart(2, '0');
        elTimer.textContent = `${m}:${s}`;
        if (secs--) setTimeout(tick, 1000);
    })();

    /* Enable button only when text entered */
    const ta = document.getElementById('entries');
    const btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    /* Save stage data via REST */
    btn.addEventListener('click', async () => {

        const lines = ta.value.split(/\r?\n/).map(t => t.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin', // send cookies
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>' // ← nonce here
            },
            body: JSON.stringify({
                stage: 'early_childhood', // or 'elementary'
                entries: lines
            })
        }).then(r => r.json());



        if (res.success) {
            alert('Early Childhood saved! (Next stage coming soon)');
            location.href = '/jeanius-assessment/wizard-stage-2/';

        } else {
            alert('Error – please try again');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>


<?php
	}
/** Stage 2 – Elementary School */
public static function render_stage_two() {

    $post_id = \Jeanius\current_assessment_id();
    if ( ! $post_id ) wp_die( 'Please log in first.' );

    /* NEW — create a REST nonce for this page */
    $rest_nonce = wp_create_nonce( 'wp_rest' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<meta charset="<?php bloginfo( 'charset' ); ?>">
<?php wp_head(); ?>
<style>
body {
    font-family: sans-serif;
    padding: 40px
}

#timer {
    font-size: 2rem;
    margin-bottom: 20px;
}

textarea {
    width: 100%;
    max-width: 600px
}
</style>
</head>

<body <?php body_class(); ?>>
    <h2>Jeanius Assessment – Timeline Wizard</h2>
    <div id="timer">5:00</div>

    <h3>Elementary School (5 – 11)</h3>
    <p>Enter 2-3 words, people, events, or experiences that describe this stage.<br>Put each on its own line.</p>

    <textarea id="entries" rows="4" placeholder="One idea per line"></textarea><br><br>
    <button id="save-btn" class="button button-primary" disabled>Save & Continue</button>

    <script>
    /* timer identical to stage 1 */
    let secs = 5 * 60,
        elT = document.getElementById('timer');
    (function t() {
        const m = String(Math.floor(secs / 60)).padStart(2, '0'),
            s = String(secs % 60).padStart(2, '0');
        elT.textContent = `${m}:${s}`;
        if (secs--) setTimeout(t, 1000)
    })();
    const ta = document.getElementById('entries'),
        btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    btn.addEventListener('click', async () => {
        const lines = ta.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';
        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin', // ← send cookies
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>' // ← nonce
            },
            body: JSON.stringify({
                stage: 'elementary', // this stage’s key
                entries: lines
            })
        }).then(r => r.json());

        if (res.success) {
            location.href = '/jeanius-assessment/wizard-stage-3/';
        } else {
            alert('Error, try again');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>
    <?php wp_footer(); ?>
</body>

</html><?php
}

/** ------------------------------------------------------------------
 *  Stage 3 – Middle School / Junior High (11 – 14)
 * ------------------------------------------------------------------*/
public static function render_stage_three() {

	// Ensure the user is logged in and has an assessment post
	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) {
		wp_die( 'Please log in first.' );
	}

	// Nonce for REST security
	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php wp_head(); ?>
    <style>
    body {
        font-family: sans-serif;
        padding: 40px;
    }

    #timer {
        font-size: 2rem;
        margin-bottom: 20px;
    }

    textarea {
        width: 100%;
        max-width: 600px;
    }
    </style>
</head>

<body <?php body_class(); ?>>

    <h2>Jeanius Assessment – Timeline Wizard</h2>
    <div id="timer">5:00</div>

    <h3>Middle School (11 – 14)</h3>
    <p>Enter 2-3 words, people, events, or experiences that describe this stage.<br>
        Put each entry on its own line.</p>

    <textarea id="entries" rows="4" placeholder="One idea per line"></textarea><br><br>
    <button id="save-btn" class="button button-primary" disabled>Save & Continue</button>

    <script>
    /* ---------- 15-minute countdown ---------- */
    let secs = 5 * 60,
        elTimer = document.getElementById('timer');
    (function tick() {
        const m = String(Math.floor(secs / 60)).padStart(2, '0'),
            s = String(secs % 60).padStart(2, '0');
        elTimer.textContent = `${m}:${s}`;
        if (secs--) setTimeout(tick, 1000);
    })();

    /* Enable button only when text present */
    const ta = document.getElementById('entries'),
        btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    /* Save via REST */
    btn.addEventListener('click', async () => {

        const lines = ta.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            },
            body: JSON.stringify({
                stage: 'middle_school',
                entries: lines
            })
        }).then(r => r.json());

        if (res.success) {
            // Redirect to Stage 4 (High School) – we'll add that screen next.
            location.href = '/jeanius-assessment/wizard-stage-4/';
        } else {
            alert('Error – please try again.');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>
<?php
}
/** ------------------------------------------------------------------
 *  Stage 4 – High School (14 – 18)
 * ------------------------------------------------------------------*/
public static function render_stage_four() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in first.' );

	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php wp_head(); ?>
    <style>
    body {
        font-family: sans-serif;
        padding: 40px
    }

    #timer {
        font-size: 2rem;
        margin-bottom: 20px;
    }

    textarea {
        width: 100%;
        max-width: 600px
    }
    </style>
</head>

<body <?php body_class(); ?>>

    <h2>Jeanius Assessment – Timeline Wizard</h2>
    <div id="timer">5:00</div>

    <h3>High School (14 – 18)</h3>
    <p>Enter 2-3 words, people, events, or experiences that describe this stage.<br>
        Put each entry on its own line.</p>

    <textarea id="entries" rows="4" placeholder="One idea per line"></textarea><br><br>
    <button id="save-btn" class="button button-primary" disabled>Save & Continue</button>

    <script>
    /* countdown */
    let secs = 5 * 60,
        elTimer = document.getElementById('timer');
    (function t() {
        const m = String(Math.floor(secs / 60)).padStart(2, '0'),
            s = String(secs % 60).padStart(2, '0');
        elTimer.textContent = `${m}:${s}`;
        if (secs--) setTimeout(t, 1000)
    })();

    const ta = document.getElementById('entries'),
        btn = document.getElementById('save-btn');
    ta.addEventListener('input', () => btn.disabled = !ta.value.trim());

    btn.addEventListener('click', async () => {
        const lines = ta.value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        btn.disabled = true;
        btn.textContent = 'Saving…';

        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/stage' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            },
            body: JSON.stringify({
                stage: 'high_school',
                entries: lines
            })
        }).then(r => r.json());

        if (res.success) {
            // Next screen will be the 5-minute review (to be built)
            location.href = '/jeanius-assessment/review/';
        } else {
            alert('Error – please try again.');
            btn.disabled = false;
            btn.textContent = 'Save & Continue';
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>
<?php
}

/** ------------------------------------------------------------------
 * 5-Minute Review – drag items to chronological order
 * ------------------------------------------------------------------*/
public static function render_review() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	$data = json_decode( get_field( 'stage_data', $post_id ) ?: '{}', true );
	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php wp_head(); ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>
    body {
        font-family: sans-serif;
        padding: 40px
    }

    .stage {
        max-width: 500px;
        margin-bottom: 30px
    }

    .stage h4 {
        margin-bottom: 5px
    }

    .stage ul {
        list-style: none;
        padding: 0;
        border: 1px solid #ccc;
        border-radius: 4px;
        min-height: 40px
    }

    .stage li {
        padding: 6px 10px;
        border-bottom: 1px solid #ddd;
        cursor: grab;
        background: #fafafa
    }

    .stage li:last-child {
        border-bottom: none
    }

    #save {
        padding: 10px 20px
    }
    </style>
</head>

<body <?php body_class(); ?>>

    <h2>Review &amp; Re-order Your Timeline (5 min)</h2>
    <p>Drag items within each stage until they’re in chronological order. You can also double-click a stage to add
        another word.</p>

    <?php foreach ( $data as $stage_key => $words ) : ?>
    <div class="stage" data-stage="<?php echo esc_attr( $stage_key ); ?>">
        <h4><?php echo ucwords( str_replace('_',' ', $stage_key) ); ?></h4>
        <ul>
            <?php foreach ( $words as $w ) : ?>
            <li contenteditable="false"><?php echo esc_html( $w ); ?></li>
            <?php endforeach; ?>
        </ul>
        <button class="add-word button">+ Add Word</button>
    </div>
    <?php endforeach; ?>

    <button id="save" class="button button-primary">Save Order &amp; Continue</button>

    <script>
    /* Make each UL sortable */
    document.querySelectorAll('.stage ul').forEach(el => {
        new Sortable(el, {
            animation: 150
        });
    });

    /* Add-word buttons */
    document.querySelectorAll('.add-word').forEach(btn => {
        btn.addEventListener('click', () => {
            const ul = btn.previousElementSibling;
            const li = document.createElement('li');
            li.textContent = '';
            li.contentEditable = 'true';
            ul.appendChild(li);
            li.focus();
        });
    });

    /* Save */
    document.getElementById('save').addEventListener('click', async () => {
        const ordered = {};
        document.querySelectorAll('.stage').forEach(stage => {
            const key = stage.dataset.stage;
            const words = [];
            stage.querySelectorAll('li').forEach(li => {
                const txt = li.textContent.trim();
                if (txt) words.push(txt);
            });
            ordered[key] = words;
        });
        const res = await fetch('<?php echo esc_url( rest_url( 'jeanius/v1/review' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            },
            body: JSON.stringify({
                ordered
            })
        }).then(r => r.json());

        if (res.success) {
            alert('Order saved! (Next step will ask you to describe each word.)');
            location.href = '/jeanius-assessment/describe/';
        } else {
            alert('Error saving – try again.');
        }
    });
    </script>

    <?php wp_footer(); ?>
</body>

</html>
<?php
}
/** --------------------------------------------------------------
 * Describe the next unfinished word
 * --------------------------------------------------------------*/
/* --------------------------------------------------------------
 * Describe screen – one word at a time
 * -------------------------------------------------------------*/
public static function render_describe() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	/* -------- life-stage order ------------------------------ */
	$stage_order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];

	/* -------- load original words (JSON string) -------------- */
	$stage_data_raw = get_field( 'stage_data', $post_id ) ?: '{}';
	$stage_data     = json_decode( $stage_data_raw, true );

	/* -------- find first word not yet described -------------- */
	$current_stage = null;
	$current_idx   = null;
	$current_word  = null;

	foreach ( $stage_order as $stage_key ) {

		$words = $stage_data[ $stage_key ] ?? [];
		$total = count( $words );
		$done  = (int) get_post_meta( $post_id, "_{$stage_key}_done", true );

		if ( $done < $total ) {
			$current_stage = $stage_key;
			$current_idx   = $done;          // 0-based
			$current_word  = $words[ $current_idx ];
			break;
		}
	}

	/* -------- everything finished ⇒ timeline ----------------- */
	if ( $current_word === null ) {
		wp_safe_redirect( '/jeanius-assessment/timeline/' );
		exit;
	}

	/* -------- prepare variables for template ---------------- */
	$display_word = is_array( $current_word ) ? $current_word['title'] : $current_word;
	$rest_nonce   = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php wp_head(); ?>
	<style>
		body{font-family:sans-serif;padding:40px;max-width:700px;margin:auto}
		label{display:block;margin-top:15px}
		button{margin-top:20px}
	</style>
</head>
<body <?php body_class(); ?>>

	<h2><?php echo esc_html( ucwords( str_replace( '_',' ', $current_stage ) ) ); ?> — Describe</h2>
	<p>You wrote: <strong><?php echo esc_html( $display_word ); ?></strong></p>

	<label>Description (1–2 sentences)
		<textarea id="desc" rows="3" style="width:100%;"></textarea>
	</label>

	<label>How do you feel about it?</label>
	<button id="pos" class="button">Positive</button>
	<button id="neg" class="button">Negative</button>

	<label>Rating (1–5)
		<input type="range" id="rate" min="1" max="5" value="3">
	</label>

	<button id="save" class="button button-primary">Save & Next</button>

	<script>
	let polarity = null;
	document.getElementById('pos').onclick = () => polarity = 'positive';
	document.getElementById('neg').onclick = () => polarity = 'negative';

	document.getElementById('save').addEventListener('click', async () => {

		const btn    = document.getElementById('save');
		const desc   = document.getElementById('desc').value.trim();
		const rating = parseInt(document.getElementById('rate').value,10)||0;

		if(!desc || !polarity){ alert('Please complete all fields'); return; }

		btn.disabled = true;

		const res = await fetch('<?php echo esc_url( rest_url( "jeanius/v1/describe" ) ); ?>',{
			method:'POST',
			credentials:'same-origin',
			headers:{
				'Content-Type':'application/json',
				'X-WP-Nonce':'<?php echo esc_js( $rest_nonce ); ?>'
			},
			body:JSON.stringify({
				stage:'<?php echo esc_js( $current_stage ); ?>',
				index:<?php echo $current_idx; ?>,
				title:`<?php echo esc_js( $display_word ); ?>`,
				description:desc,
				polarity:polarity,
				rating:rating
			})
		}).then(r=>r.json()).catch(()=>({success:false}));

		if(res.success){
			window.location.href = '/jeanius-assessment/describe/';
		}else{
			alert('Save failed — please try again.');
			btn.disabled = false;
		}
	});
	</script>

<?php wp_footer(); ?></body></html><?php
}


/** ------------------------------------------------------------------
 * Timeline plot – fixed-size blue dots, colored stage brackets,
 * CTA button.
 * ------------------------------------------------------------------*/
public static function render_timeline() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	$raw   = \Jeanius\Rest::get_timeline_data( $post_id );
	$order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];
	$stageColors = [
		'early_childhood' => '#3498db',
		'elementary'      => '#2ecc71',
		'middle_school'   => '#f1c40f',
		'high_school'     => '#e74c3c',
	];
	/* keep drag order inside each stage */
	$x=0; $points=[]; $ranges=[];
	foreach($order as $stage){
		$start=$x;
		foreach(array_values(array_filter($raw,fn($r)=>$r['stage']===$stage)) as $p){
			$p['x']=$x++;                              // preserve order
			$p['y']=$p['polarity']==='negative'? -$p['rating'] : $p['rating'];
			$points[]=$p;
		}
		$end=$x-1; $x+=1.5;                          // gap
		$ranges[]=[ 'stage'=>$stage,'start'=>$start,'end'=>$end ];
	}
	$json=wp_json_encode($points); $rangesJson=wp_json_encode($ranges);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>"><?php wp_head();?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
    body {
        font-family: sans-serif;
        padding: 40px;
        max-width: 900px;
        margin: auto;
        text-align: center
    }

    canvas {
        max-width: 860px
    }

    .cta {
        margin-top: 30px
    }
    </style>
</head>

<body <?php body_class();?>>

    <h2>Your Life Timeline</h2>
    <canvas id="timeline"></canvas>
    <button class="button button-primary cta" onclick="location.href='/jeanius-assessment/results/'">
        See Your Jeanius Results
    </button>

    <script>
    (() => {
        const spinner = document.createElement('p');
        spinner.id = 'genSpinner';
        spinner.textContent = 'Preparing your Jeanius report…';
        document.body.appendChild(spinner);

        fetch('<?php echo esc_url( rest_url( 'jeanius/v1/generate' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( "wp_rest" ) ); ?>'
            }
        }).then(r => r.json()).then(() => {
            spinner.remove(); // hide spinner when done
        }).catch(() => {
            spinner.textContent = '⚠️ Report generation failed. You can still view the timeline.';
        });
    })();
    </script>



    <script>
    const pts = <?php echo $json;?>;
    const ranges = <?php echo $rangesJson;?>;
    const stageClr = <?php echo wp_json_encode($stageColors);?>;

    const ctx = document.getElementById('timeline');
    new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Experiences',
                data: pts,
                backgroundColor: '#2980b9', // all dots same blue
                radius: 8 // fixed size
            }]
        },
        options: {
            scales: {
                x: {
                    title: {
                        display: false
                    },
                    ticks: {
                        display: false
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Impact (Positive ↑ | Negative ↓)'
                    },
                    min: -5,
                    max: 5,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const r = ctx.raw;
                            return [
                                r.label,
                                r.description,
                                (r.polarity === 'negative' ? 'Negative' : 'Positive') + ' · Rating ' + r
                                .rating
                            ];
                        }
                    }
                }
            },
            plugins: [{ // custom brackets plugin
                id: 'stageBrackets',
                afterDraw(chart, args, opts) {
                    const {
                        ctx,
                        chartArea: {
                            bottom,
                            left,
                            right
                        }
                    } = chart;
                    const y = bottom + 10;
                    ctx.save();
                    ctx.font = '12px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'top';
                    ranges.forEach(rg => {
                        const startPx = chart.scales.x.getPixelForValue(rg.start) - 5;
                        const endPx = chart.scales.x.getPixelForValue(rg.end) + 5;
                        /* line */
                        ctx.strokeStyle = stageClr[rg.stage];
                        ctx.lineWidth = 3;
                        ctx.beginPath();
                        ctx.moveTo(startPx, y);
                        ctx.lineTo(endPx, y);
                        ctx.stroke();
                        /* down ticks */
                        ctx.beginPath();
                        ctx.moveTo(startPx, y);
                        ctx.lineTo(startPx, y + 6);
                        ctx.moveTo(endPx, y);
                        ctx.lineTo(endPx, y + 6);
                        ctx.stroke();
                        /* label */
                        const label = rg.stage.replace('_', ' ');
                        ctx.fillStyle = stageClr[rg.stage];
                        ctx.fillText(label, (startPx + endPx) / 2, y + 8);
                    });
                    ctx.restore();
                }
            }]
        }
    });
    </script>

    <?php wp_footer();?>
</body>

</html><?php
}


/** ------------------------------------------------------------------
 * Results screen – prints pre-formatted HTML from ACF “_md_copy” fields.
 * If those fields are still empty it triggers /generate once, then reloads.
 * ------------------------------------------------------------------*/
public static function render_results() {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) wp_die( 'Please log in.' );

	/* ── Grab HTML blocks ────────────────────────────────────────── */
	$sections = [
		'Ownership Stakes'     => get_field( 'ownership_stakes_md_copy',     $post_id ),
		'Life Messages'        => get_field( 'life_messages_md_copy',        $post_id ),
		'Transcendent Threads' => get_field( 'transcendent_threads_md_copy', $post_id ),
		'Sum of Your Jeanius'  => get_field( 'sum_jeanius_md_copy',          $post_id ),
		'College Essay Topics' => get_field( 'essay_topics_md_copy',         $post_id ),
	];

	$is_ready   = array_filter( $sections ) !== [];      // any HTML present?
	$rest_nonce = wp_create_nonce( 'wp_rest' );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <?php wp_head(); ?>
    <style>
    body {
        font-family: Georgia, serif;
        padding: 40px;
        max-width: 860px;
        margin: auto
    }

    h2 {
        color: #003d7c;
        margin-top: 2.2rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 4px
    }

    #loading {
        font-size: 1.1rem
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 1em
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 6px
    }

    ul {
        margin-left: 1.2em
    }
    </style>
</head>

<body <?php body_class(); ?>>

    <h1>Your Jeanius Insights Report</h1>

    <div id="loading" <?php if ( $is_ready ) echo ' style="display:none"'; ?>>
        Generating your report… this may take up to a minute.
    </div>

    <div id="report">
        <?php
	    if ( $is_ready ) {
	        foreach ( $sections as $title => $html ) {
	            if ( ! $html ) continue;
	            echo '<section>';
	            echo '<h2>'. esc_html( $title ) .'</h2>';
	            echo wp_kses_post( $html );   // already HTML
	            echo '</section>';
	        }
	    }
	    ?>
    </div>

    <?php if ( ! $is_ready ) : ?>
    <script>
    fetch('<?php echo esc_url( rest_url( 'jeanius/v1/generate' ) ); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': '<?php echo esc_js( $rest_nonce ); ?>'
            }
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'ready') {
                location.reload(); // re-load to pull the new HTML fields
            } else {
                document.getElementById('loading').textContent =
                    '⚠️ Error generating report — please reload.';
            }
        })
        .catch(() => {
            document.getElementById('loading').textContent =
                '⚠️ Network error — please reload.';
        });
    </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>

</html><?php
}










}