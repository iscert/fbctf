<?hh // strict

class Token extends Model {
  private function __construct(
    private int $id,
    private int $used,
    private int $team_id, 
    private string $token,
    private string $created_ts,
    private string $use_ts
    ) {
  }

  public function getId(): int {
    return $this->id;
  }

  public function getUsed(): int {
    return $this->used;
  }

  public function getTeamId(): int {
    return $this->team_id;
  }

  public function getToken(): string {
    return $this->token;
  }

  public function getCreatedTs(): string {
    return $this->created_ts;
  }

  public function getUseTs(): string {
    return $this->use_ts;
  }

  private static function tokenFromRow(Map<string, string> $row): Token {
    return new Token(
      intval(must_have_idx($row, 'id')),
      intval(must_have_idx($row, 'used')),
      intval(must_have_idx($row, 'team_id')),
      must_have_idx($row, 'token'),
      must_have_idx($row, 'created_ts'),
      must_have_idx($row, 'use_ts'),
    );
  }

  private static function generate(): string {
    $token_len = 15;
    $crypto_strong = True;
    return md5(
      base64_encode(
        openssl_random_pseudo_bytes(
          $token_len,
          $crypto_strong,
        )
      )
    );
  }

  // Create token.
  public static async function genCreate(): Awaitable<void> {
    $db = await self::genDb();
    $tokens = array();
    $query = array();
    $token_number = 50;
    for ($i = 0; $i < $token_number; $i++) {
      $token = self::generate();
      await $db->queryf(
        'INSERT INTO registration_tokens (token, created_ts) VALUES (%s, NOW())',
        $token,
      );
    }
  }

  public static async function genExport(): Awaitable<void> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM registration_tokens WHERE used = 0',
    );
    // TODO
  }

  public static async function genDelete(
    string $token,
  ): Awaitable<void> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'DELETE from registration_tokens WHERE token = %s LIMIT 1',
      $token,
    );
  }

  // Get all tokens.
  public static async function genAllTokens(): Awaitable<array<Token>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM registration_tokens',
    );

    $tokens = array();
    foreach ($result->mapRows() as $row) {
      $tokens[] = self::tokenFromRow($row);
    }

    return $tokens;
  }

  // Get all available tokens.
  public static async function genAllAvailableTokens(
  ): Awaitable<array<Token>> {
    $db = await self::genDb();
    $result = await $db->queryf(
      'SELECT * FROM registration_tokens WHERE used = 0',
    );

    $tokens = array();
    foreach ($result->mapRows() as $row) {
      $tokens[] = self::tokenFromRow($row);
    }

    return $tokens;
  }

  // Check to see if the level is active.
  public static async function genCheck(
    string $token,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM registration_tokens WHERE used = 0 AND token = %s',
      $token,
    );

    if ($result->numRows() > 0) {
      invariant($result->numRows() === 1, 'Expected exactly one result');
      return (intval($result->mapRows()[0]['COUNT(*)']) > 0);
    } else {
      return false;
    }
  }

  // Use a token for a team registration.
  public static async function genUse(
    string $token,
    int $team_id,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE registration_tokens SET used = 1, team_id = %d, use_ts = NOW() WHERE token = %s LIMIT 1',
      $team_id,
      $token,
    );
  }
}