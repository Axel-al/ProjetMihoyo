<?php
namespace Models;

class Personnage {
    private ?string $id = null;
    private string $name;
    private string $element;
    private string $unitclass;
    private int $rarity;
    private ?string $origin = null;
    private string $urlImg;
    private ?string $description = null;

    public function getId(): ?string {
        return $this->id;
    }

    public function setId(?string $id = null): void {
        $this->id = $id ?? uniqid();
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getElement(): string {
        return $this->element;
    }

    public function setElement(string $element): void {
        $this->element = $element;
    }

    public function getUnitclass(): string {
        return $this->unitclass;
    }

    public function setUnitclass(string $unitclass): void {
        $this->unitclass = $unitclass;
    }

    public function getRarity(): int {
        return $this->rarity;
    }

    public function setRarity(int $rarity): void {
        $this->rarity = $rarity;
    }

    public function getOrigin(): ?string {
        return $this->origin;
    }

    public function setOrigin(?string $origin): void {
        $this->origin = $origin;
    }

    public function getUrlImg(): string {
        return $this->urlImg;
    }

    public function setUrlImg(string $urlImg): void {
        $this->urlImg = $urlImg;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): void {
        $this->description = $description;
    }

    public function hydrate(array $data): self {
        foreach ($data as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
        return $this;
    }

    public function isInitialized($attribute): bool {
        return array_key_exists($attribute, get_object_vars($this));
    }
}
