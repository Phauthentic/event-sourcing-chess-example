<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess\Service;

use App\Domain\Chess\Service\LongAlgebraicNotationParser;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class LongAlgebraicNotationParserTest extends TestCase
{
    private LongAlgebraicNotationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LongAlgebraicNotationParser();
    }

    public function testRegularMove(): void
    {
        $result = $this->parser->parseMove('e2-e4');
        $this->assertEquals([
            'piece' => 'P',
            'from' => 'e2',
            'to' => 'e4',
            'promotion' => null,
            'check' => null
        ], $result);

        $board = $this->parser->getBoard();
        $this->assertArrayNotHasKey('e2', $board);
        $this->assertEquals('whiteP', $board['e4']);
    }

    public function testPieceMoveWithCapture(): void
    {
        $this->parser->parseMove('e2-e4');
        $this->parser->parseMove('d7-d5');
        $result = $this->parser->parseMove('e4-d5');

        $this->assertEquals([
            'piece' => 'P',
            'from' => 'e4',
            'to' => 'd5',
            'promotion' => null,
            'check' => null
        ], $result);

        $board = $this->parser->getBoard();
        $this->assertArrayNotHasKey('e4', $board);
        $this->assertArrayNotHasKey('d7', $board);
        $this->assertEquals('whiteP', $board['d5']);
    }

    public function testPawnPromotion(): void
    {
        $this->parser->parseMove('a2-a4');
        $this->parser->parseMove('b7-b5');
        $this->parser->parseMove('a4-b5');
        $this->parser->parseMove('h7-h6');
        $this->parser->parseMove('b5-b6');
        $this->parser->parseMove('h6-h5');
        $this->parser->parseMove('b6-b7');
        $this->parser->parseMove('h5-h4');
        $result = $this->parser->parseMove('b7-b8=Q');

        $this->assertEquals([
            'piece' => 'P',
            'from' => 'b7',
            'to' => 'b8',
            'promotion' => 'Q',
            'check' => null
        ], $result);

        $board = $this->parser->getBoard();
        $this->assertArrayNotHasKey('b7', $board);
        $this->assertEquals('whiteQ', $board['b8']);
    }

    public function testKingsideCastling(): void
    {
        $result = $this->parser->parseMove('O-O');
        $this->assertEquals([
            'castling' => 'kingside',
            'player' => 'white'
        ], $result);

        $board = $this->parser->getBoard();
        $this->assertArrayNotHasKey('e1', $board);
        $this->assertArrayNotHasKey('h1', $board);
        $this->assertEquals('whiteK', $board['g1']);
        $this->assertEquals('whiteR', $board['f1']);
    }

    public function testQueensideCastling(): void
    {
        $this->parser->parseMove('e2-e4'); // Move to change current player
        $result = $this->parser->parseMove('O-O-O');
        $this->assertEquals([
            'castling' => 'queenside',
            'player' => 'black'
        ], $result);

        $board = $this->parser->getBoard();
        $this->assertArrayNotHasKey('e8', $board);
        $this->assertArrayNotHasKey('a8', $board);
        $this->assertEquals('blackK', $board['c8']);
        $this->assertEquals('blackR', $board['d8']);
    }

    public function testMoveWithCheck(): void
    {
        $result = $this->parser->parseMove('Qd1-f3+');
        $this->assertEquals([
            'piece' => 'Q',
            'from' => 'd1',
            'to' => 'f3',
            'promotion' => null,
            'check' => '+'
        ], $result);
    }

    public function testMoveWithCheckmate(): void
    {
        $result = $this->parser->parseMove('Qd1-f7#');
        $this->assertEquals([
            'piece' => 'Q',
            'from' => 'd1',
            'to' => 'f7',
            'promotion' => null,
            'check' => '#'
        ], $result);
    }

    public function testInvalidMoveNotation(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid move notation: e2-e5-e6');
        $this->parser->parseMove('e2-e5-e6');
    }

    public function testAlternatingPlayers(): void
    {
        $this->parser->parseMove('e2-e4');
        $board = $this->parser->getBoard();
        $this->assertEquals('whiteP', $board['e4']);

        $this->parser->parseMove('e7-e5');
        $board = $this->parser->getBoard();
        $this->assertEquals('blackP', $board['e5']);

        $this->parser->parseMove('Ng1-f3');
        $board = $this->parser->getBoard();
        $this->assertEquals('whiteN', $board['f3']);
    }

    /**
     * @dataProvider moveExplanationProvider
     */
    public function testExplainMove(string $move, string $expectedExplanation): void
    {
        $this->assertEquals($expectedExplanation, $this->parser->explainMove($move));
    }

    public function moveExplanationProvider(): array
    {
        return [
            // Pawn moves
            ['e2-e4', 'The white Pawn moves from e2 to e4.'],
            ['d7-d5', 'The white Pawn moves from d7 to d5.'],

            // Piece moves
            ['Ng1-f3', 'The white Knight moves from g1 to f3.'],
            ['Bf1-c4', 'The white Bishop moves from f1 to c4.'],
            ['Qd1-e2', 'The white Queen moves from d1 to e2.'],
            ['Ra1-d1', 'The white Rook moves from a1 to d1.'],
            ['Ke1-e2', 'The white King moves from e1 to e2.'],

            // Pawn promotion
            ['h7-h8=Q', 'The white Pawn moves from h7 to h8 and is promoted to a Queen.'],
            ['a2-a1=R', 'The white Pawn moves from a2 to a1 and is promoted to a Rook.'],
            ['b7-b8=N', 'The white Pawn moves from b7 to b8 and is promoted to a Knight.'],
            ['g2-g1=B', 'The white Pawn moves from g2 to g1 and is promoted to a Bishop.'],

            // Moves resulting in check
            ['Qd1-f3+', 'The white Queen moves from d1 to f3, putting the opponent\'s King in check.'],
            ['e7-e8=Q+', 'The white Pawn moves from e7 to e8 and is promoted to a Queen, putting the opponent\'s King in check.'],

            // Moves resulting in checkmate
            ['Qh5-e8#', 'The white Queen moves from h5 to e8, checkmating the opponent\'s King.'],
            ['g7-g8=R#', 'The white Pawn moves from g7 to g8 and is promoted to a Rook, checkmating the opponent\'s King.'],

            // Castling
            ['O-O', 'The white King castles on the kingside. The King moves from e1 to g1, and the Rook moves from h1 to f1.'],
            ['O-O-O', 'The white King castles on the queenside. The King moves from e1 to c1, and the Rook moves from a1 to d1.'],
        ];
    }

    public function testExplainMoveWithChangingPlayers(): void
    {
        $this->assertEquals('The white Pawn moves from e2 to e4.', $this->parser->explainMove('e2-e4'));
        $this->parser->parseMove('e2-e4'); // Change current player to black
        $this->assertEquals('The black Pawn moves from e7 to e5.', $this->parser->explainMove('e7-e5'));
        $this->parser->parseMove('e7-e5'); // Change current player back to white
        $this->assertEquals('The white King castles on the kingside. The King moves from e1 to g1, and the Rook moves from h1 to f1.', $this->parser->explainMove('O-O'));
    }

    public function testExplainMoveWithInvalidNotation(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid move notation: e2-e5-e6');
        $this->parser->explainMove('e2-e5-e6');
    }
}
