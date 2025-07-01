<?php
namespace Jeanius;

class Rest {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {

		register_rest_route( 'jeanius/v1', '/stage', [
			'methods'             => 'POST',
			'permission_callback' => function () { return is_user_logged_in(); },
			'callback'            => [ __CLASS__, 'save_stage' ],
		] );
		register_rest_route( 'jeanius/v1', '/review', [
			'methods'             => 'POST',
			'permission_callback' => function(){ return is_user_logged_in(); },
			'callback'            => [ __CLASS__, 'save_order' ],
		] );
		/* ---------- save description for one word ---------- */
register_rest_route( 'jeanius/v1', '/describe', [
    'methods'             => 'POST',
    'permission_callback' => function(){ return is_user_logged_in(); },
    'callback'            => [ __CLASS__, 'save_description' ],
] );

// ────────────────────────────────
// Generate Jeanius report (OpenAI)
// POST /wp-json/jeanius/v1/generate
// ────────────────────────────────
register_rest_route( 'jeanius/v1', '/generate', [
	'methods'             => 'POST',
	'permission_callback' => fn() => is_user_logged_in(),
	'callback'            => [ __CLASS__, 'generate_report' ],
] );


	}

	public static function save_stage( \WP_REST_Request $r ) {

		$post_id   = \Jeanius\current_assessment_id();          // ✔ always get my post
		if ( ! $post_id ) {
			return new \WP_Error( 'login_required', 'Login first', [ 'status'=>401 ] );
		}

		$stage_key = sanitize_text_field( $r->get_param( 'stage' ) );
		$entries   = $r->get_param( 'entries' );

		if ( ! $stage_key || empty( $entries ) ) {
			return new \WP_Error( 'missing', 'Missing data', [ 'status'=>400 ] );
		}

		$data = json_decode( get_field( 'stage_data', $post_id ) ?: '{}', true );
		$data[ $stage_key ] = array_values(
			array_filter( array_map( 'sanitize_text_field', $entries ) )
		);

		\update_field( 'stage_data', wp_json_encode( $data ), $post_id );

		return [ 'success' => true ];
	}
	/** ------------------------------------------------------------------
 * Save reordered words during 5-minute review
 * POST /jeanius/v1/review
 * Body: { "ordered": { "early_childhood":[...], "elementary":[...] ... } }
 * ------------------------------------------------------------------*/
public static function save_order( \WP_REST_Request $r ) {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) {
		return new \WP_Error( 'login', 'Login required', [ 'status'=>401 ] );
	}

	$ordered = $r->get_param( 'ordered' );
	if ( ! is_array( $ordered ) ) {
		return new \WP_Error( 'bad', 'Missing ordered data', [ 'status'=>400 ] );
	}

	\update_field( 'stage_data', wp_json_encode( $ordered ), $post_id );
	return [ 'success' => true ];
} 

// helper to read / write progress count
private static function stage_counter( int $post_id, string $stage, ?int $set = null ) {
    $key = "_{$stage}_done";
    if ( $set !== null ) {
        update_post_meta( $post_id, $key, $set );
    }
    return (int) get_post_meta( $post_id, $key, true );
}


public static function save_description( \WP_REST_Request $r ){

    $post_id = \Jeanius\current_assessment_id();
    if( ! $post_id ) return new \WP_Error('login','Login', ['status'=>401]);

    $stage   = sanitize_text_field( $r['stage'] );
    $index   = (int) $r['index'];
    $desc    = sanitize_textarea_field( $r['description'] );
    $pol     = $r['polarity'] === 'negative' ? 'negative' : 'positive';
    $rating  = min(5,max(1,(int)$r['rating']));

    /* --------- append to full_stage_data ------------ */
    $full = json_decode( get_field('full_stage_data',$post_id) ?: '{}', true );
    $full[$stage][] = [
        'title'       => $r['title'],
        'description' => $desc,
        'polarity'    => $pol,
        'rating'      => $rating,
    ];
    update_field( 'full_stage_data', wp_json_encode( $full ), $post_id );

    /* --------- bump progress counter ---------------- */
    $done = self::stage_counter( $post_id, $stage );
    self::stage_counter( $post_id, $stage, $done + 1 );

    return ['success'=>true];
}


public static function get_timeline_data( int $post_id ) : array {

	$raw  = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );
	$out  = [];
	$order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];
	foreach ( $order as $stage_idx => $stage_key ) {
		if ( empty( $raw[$stage_key] ) ) continue;
		foreach ( $raw[$stage_key] as $seq => $item ) {
			// safeguard: cast plain strings (shouldn’t exist now) to minimal object
			if ( ! is_array( $item ) ) {
				$item = [ 'title'=>$item, 'description'=>'', 'polarity'=>'positive', 'rating'=>3 ];
			}
			$out[] = [
				'label'       => $item['title'],
				'stage'       => $stage_key,
				'stage_order' => $stage_idx,
				'seq'         => $seq,
				'description' => $item['description'],
				'polarity'    => $item['polarity'],
				'rating'      => (int) $item['rating'],
			];
		}
	}
	return $out;
}
/* --------------------------------------------------------------
 * generate_report() – 5 sequential GPT calls
 * --------------------------------------------------------------*/
public static function generate_report( \WP_REST_Request $r ) {

	$post_id = \Jeanius\current_assessment_id();
	if ( ! $post_id ) return new \WP_Error( 'login', 'Login required', [ 'status'=>401 ] );

	// If HTML copy fields already filled, skip regeneration
	if ( get_field( 'ownership_stakes_md_copy', $post_id ) ) {
		return [ 'status' => 'ready' ];
	}

	$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
	if ( empty( $api_key ) ) return new \WP_Error( 'key', 'OpenAI key missing', [ 'status'=>500 ] );

	$stage_data = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );

	/* ---------- STEP 1 ─ Ownership Stakes ---------- */
	$stakes_md = self::call_openai(
		$api_key,
		self::prompt_ownership( $stage_data )
	);
	update_field( 'ownership_stakes_md',      $stakes_md, $post_id );
	update_field( 'ownership_stakes_md_copy', $stakes_md, $post_id );

	/* ---------- STEP 2 ─ Life Messages ------------ */
	$life_md = self::call_openai(
		$api_key,
		self::prompt_life_messages( $stakes_md )
	);
	update_field( 'life_messages_md',      $life_md, $post_id );
	update_field( 'life_messages_md_copy', $life_md, $post_id );

	/* ---------- STEP 3 ─ Transcendent Threads ----- */
	$threads_md = self::call_openai(
		$api_key,
		self::prompt_threads( $stakes_md, $stage_data )
	);
	update_field( 'transcendent_threads_md',      $threads_md, $post_id );
	update_field( 'transcendent_threads_md_copy', $threads_md, $post_id );

	/* ---------- STEP 4 ─ Sum of Jeanius ---------- */
	$sum_md = self::call_openai(
		$api_key,
		self::prompt_sum( $stakes_md, $life_md, $threads_md )
	);
	update_field( 'sum_jeanius_md',      $sum_md, $post_id );
	update_field( 'sum_jeanius_md_copy', $sum_md, $post_id );

	// just above /* STEP 5 – College Essay Topics */
	$colleges = get_field( 'target_colleges', $post_id );   // ACF text / repeater
	$colleges = is_array( $colleges ) ? array_filter( $colleges ) : [];

	/* ---------- STEP 5 ─ College Essay Topics ----- */
	$essay_md = self::call_openai(
		$api_key,
		self::prompt_essays( $stakes_md, $threads_md, $stage_data, $colleges )
	);
	
	update_field( 'essay_topics_md',      $essay_md, $post_id );
	update_field( 'essay_topics_md_copy', $essay_md, $post_id );

	/* ---------- Store full raw markdown ------------ */
	$full = "## Ownership Stakes\n$stakes_md\n\n".
	        "## Life Messages\n$life_md\n\n".
	        "## Transcendent Threads\n$threads_md\n\n".
	        "## Sum of Your Jeanius\n$sum_md\n\n".
	        "## College Essay Topics\n$essay_md";
	update_field( 'jeanius_report_md', $full, $post_id );

	return [ 'status' => 'ready' ];
}

/** Call OpenAI and return the assistant’s text */
private static function call_openai( string $key, array $messages ) : string {

	$resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
		'timeout' => 60,
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $key,
		],
		'body' => wp_json_encode( [
			'model'       => 'gpt-4o-mini',
			'temperature' => 0.7,
			'messages'    => $messages,
		] ),
	] );

	if ( is_wp_error( $resp ) ) return '';
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	return trim( $data['choices'][0]['message']['content'] ?? '' );
}



private static function prompt_ownership( array $data ) : array {
	return [
	  ['role'=>'system','content'=>'You are a storytelling and identity analysis expert. The user will input structured data about key life events by life stage. Analyze all events, weighing emotional intensity (rating), emotional direction (polarity), and themes in the description. Extract the 7 most dominant life experience categories and return them as "Ownership Stakes". These are areas where the person holds deep lived experience and credibility.\n\n*Examples of ownership stakes from other well-known narratives:\nExample 1: Bruce Springsteen owns…\n• blue-collar ethos\n• small-town sensibility\nExample 2: Mother Teresa owns…\n• extreme compassion\n• dignity in death\nExample 3: Abe Lincoln owns…\n• human equality\n• personal character\nExample 4: Rosa Parks owns…\n• personal conviction\n• social progress\n\n**You have an ownership stake in the topics listed below, based on your life experience.\nSteph Hauser has an ownership stake in:\n• Family dysfunction\n• Adventure\n• Freedom\n• Addiction and Recovery\n• Parenting\n• Conflict\n• Surrender\n• Athletics\n• Community'],
	  ['role'=>'user',  'content'=>"Here is the data:\n\n".wp_json_encode($data)."\n\nReturn only:\nOwnership Stakes: [list of 7 key categories]"]
	];
}

private static function prompt_life_messages( string $stakes_md ) : array {
	return [
	  ['role'=>'system','content'=>'You are a storytelling coach. Based on the following Ownership Stakes, write 1 powerful, short life message for each—something the individual can credibly say based on lived experience. Each message should be emotionally resonant and phrased like a personal truth.\n\n*The messages listed below are riffs off your Ownership Stakes listed above. They are phrases that your narrative has given you credibility to speak.\nSteph Hauser’s life says…\n• On family dysfunction: ‘That story is not your story.’\n• On adventure: ‘Say yes to the big thing.’\n• On freedom: ‘Break those chains.’\n• On addiction and recovery: ‘There’s help and hope for you.’\n• On parenting: ‘Just hold on and let go.’\n• On conflict: ‘What is truly in conflict?’\n• On surrender: ‘It is where the miracle becomes possible.’\n• On athletics: ‘You can do more than you think you can.’\n• On community: ‘Get in here.’'],
	  ['role'=>'user',  'content'=>"Ownership Stakes:\n$stakes_md"]
	];
}

private static function prompt_threads( string $stakes_md, array $data ) : array {
	return [
	  ['role'=>'system','content'=>'You are a narrative development expert. Based on the provided life events and ownership stakes, identify the individuals Transcendent Pattern. Select one Confrontation Thread, one Bridge Thread, and one Payoff Thread from the following list:\n\n1. Love\n2. Loss\n3. Family\n4. Hope\n5. Truth\n6. Mystery\n7. Loyalty\n8. Simplicity\n9. Redemption\n10. Security\n11. Triumph\n12. Progress\n13. Faith\n14. Sacrifice\n15. Grace\n16. Beauty\n17. Joy\n18. Identity\n19. Freedom\n20. Resilience\n21. Innovation\n22. Contribution\n\nExplain each selected thread in 1-2 sentences.\n\n*These are the most prominent threads in your narrative (chosen from a universal list of 22) that are woven through your life and knit your life into the lives of everyone in your audience.\nSteph Hauser’s life follows the Transcendent Pattern:\nMystery >>> Resilience >>> Truth\nThread #1 (“Inciting Thread”): MYSTERY\n• You view the unknowns of life—good and bad—as adventures worth embracing; as a result, your life inspires and invites others to take the same approach.\nThread #2 (“Bridge Thread”): RESILIENCE\n• You possess a tenacious posture that compels you to see decisions and circumstances through, for the benefit of you and your tribe.\nThread #3 (“Payoff Thread”): TRUTH\n• You are rewarded by the discovery of deeper realities and truths, for yourself and others, that result from a life of bravery and determination.'],
	  ['role'=>'user',  'content'=>"Ownership Stakes:\n$stakes_md\n\nHere is the data:\n\n".wp_json_encode($data)]
	];
}

private static function prompt_sum( string $stakes_md, string $life_md, string $threads_md ) : array {
	return [
	  ['role'=>'system','content'=>'You are a career and purpose guide. Based on Ownership Stakes, Life Messages, and Transcendent Threads, write a 5-sentence summary of this individual’s core wiring—what environments they thrive in, what they value, and what communities or causes resonate most deeply.'],
	  ['role'=>'user','content'=>"Ownership Stakes: [$stakes_md]\nLife Messages: [$life_md]\nTranscendent Threads: [$threads_md]\n\nReturn the summary under the heading 'Sum of Your Jeanius'." ]
	];
}

private static function prompt_essays( string $stakes_md,
                                       string $threads_md,
                                       array  $data,
                                       array  $colleges ) : array {

    $college_line = empty($colleges)
        ? 'There are no target colleges.'
        : 'Target colleges: '.implode(', ', $colleges).'.';

    return [
      [
        'role'=>'system',
        'content'=>"You are a college-essay strategist. Use ONLY the colleges the user "
                 ."provided when you write tailoring tips. NEVER invent additional schools.\n\n"
                 ."${college_line}\n\n"
                 ."For each of five essay topics include:\n"
                 ."1. Title\n2. Rationale (2-3 sentences)\n"
                 ."3. Writing outline (5 bullets)\n"
                 ."4. Tailoring Tips – **one sub-bullet per target college above**."
      ],
      [
        'role'=>'user',
        'content'=>"Ownership Stakes:\n$stakes_md\n\n"
                 ."Transcendent Threads:\n$threads_md\n\n"
                 ."Life-stage data JSON:\n".wp_json_encode($data)
      ]
    ];
}



/**
 * Parse the GPT markdown into sections and save each ACF field.
 */
private static function save_report_sections( int $post_id, string $md ) : void {

	// Always keep the raw Markdown
	update_field( 'jeanius_report_md', $md, $post_id );

	/* 1 ─ split Markdown into sections (case-insensitive) */
	preg_match_all(
		'/^##\s+(.+?)\s*$\R([\s\S]+?)(?=^##\s|\z)/mi',
		$md, $m, PREG_SET_ORDER
	);

	$sections = [];
	foreach ( $m as $match ) {
		$sections[ strtolower( trim( $match[1] ) ) ] = trim( $match[2] );
	}

	/* 2 ─ simple map header → textarea + wysiwyg field */
	$map = [
		'ownership stakes'      => ['ownership_stakes_md',      'ownership_stakes_md_copy'],
		'life messages'         => ['life_messages_md',         'life_messages_md_copy'],
		'transcendent threads'  => ['transcendent_threads_md',  'transcendent_threads_md_copy'],
		'sum of your jeanius'   => ['sum_jeanius_md',           'sum_jeanius_md_copy'],
		'college essay topics'  => ['essay_topics_md',          'essay_topics_md_copy'],
	];

	foreach ( $map as $header => [$md_field,$html_field] ) {
		if ( ! isset( $sections[ $header ] ) ) continue;

		// 2a  save raw markdown
		update_field( $md_field, $sections[ $header ], $post_id );

		// 2b  convert to HTML via OpenAI & save into WYSIWYG field
		$html = self::markdown_to_html( $sections[ $header ] );
		update_field( $html_field, $html, $post_id );
	}
}

/**
 * Convert a single Markdown block to semantic HTML through OpenAI.
 * Returns plain HTML (no enclosing <html> / <body> tags).
 */
private static function markdown_to_html( string $markdown ) : string {

	$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
	if ( ! $api_key ) return $markdown;   // fallback: leave md unchanged

	$body = [
		'model'     => 'gpt-4o-mini',
		'max_tokens'=> 2048,
		'temperature'=> 0,
		'messages'  => [
			[ 'role'=>'system', 'content'=>'You are a Markdown to HTML converter. Return ONLY valid HTML inside <section> without additional commentary.' ],
			[ 'role'=>'user',   'content'=> $markdown ]
		],
	];

	$response = wp_remote_post(
		'https://api.openai.com/v1/chat/completions',
		[
			'timeout'=>40,
			'headers'=>[
				'Content-Type'=>'application/json',
				'Authorization'=>'Bearer '.$api_key
			],
			'body'=> wp_json_encode( $body )
		]
	);

	if ( is_wp_error( $response ) ) return $markdown;

	$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
	return $parsed['choices'][0]['message']['content'] ?? $markdown;
}



}