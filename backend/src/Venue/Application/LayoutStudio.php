<?php

declare(strict_types=1);

namespace App\Venue\Application;

use App\Shared\Domain\Exception\NotFoundException;
use App\Venue\Application\Dto\LayoutSpecInput;
use App\Venue\Application\Dto\LayoutView;
use App\Venue\Domain\Model\Venue;
use App\Venue\Domain\VenueRepository;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Studio use cases for hall configurations: list, preview, create, clone, archive. */
final readonly class LayoutStudio
{
    public function __construct(
        private VenueRepository $venues,
        private LayoutGenerator $generator,
        private LayoutService $layouts,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @return list<LayoutView>
     */
    public function list(): array
    {
        return array_map(
            static fn ($layout): LayoutView => LayoutView::of($layout, withSeats: false),
            $this->venues->layoutsOf($this->venue(), includeArchived: true),
        );
    }

    public function view(string $id): LayoutView
    {
        return LayoutView::of($this->layouts->get($this->uuid($id)));
    }

    /** Generates the plan in memory, nothing saved: the form's live preview. */
    public function preview(LayoutSpecInput $input): LayoutView
    {
        return LayoutView::of($this->generator->generate($this->venue(), $this->spec($input)), persisted: false);
    }

    public function create(LayoutSpecInput $input): LayoutView
    {
        $layout = $this->generator->generate($this->venue(), $this->spec($input));
        $this->venues->saveLayout($layout);

        return LayoutView::of($layout);
    }

    public function clone(string $id, ?string $name): LayoutView
    {
        return LayoutView::of($this->layouts->clone($this->uuid($id), $name));
    }

    public function archive(string $id): void
    {
        $this->layouts->archive($this->uuid($id));
    }

    private function spec(LayoutSpecInput $input): Dto\LayoutSpec
    {
        $spec = $input->toSpec();
        $violations = $this->validator->validate($spec);
        if (\count($violations) > 0) {
            throw new ValidationFailedException($spec, $violations);
        }

        return $spec;
    }

    private function venue(): Venue
    {
        return $this->venues->main() ?? throw new LogicException('No venue configured.');
    }

    private function uuid(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFoundException::of('Configuration', $id);
    }
}
