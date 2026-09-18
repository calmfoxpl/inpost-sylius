<?php

declare(strict_types=1);

namespace Calmfox\InPostBundle\Repository;

use Calmfox\InPostBundle\Entity\InPostShipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;

/** @extends ServiceEntityRepository<InPostShipment> */
class InPostShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InPostShipment::class);
    }

    public function findOneByShipment(ShipmentInterface $shipment): ?InPostShipment
    {
        if (null === $shipment->getId()) {
            return null;
        }

        return $this->findOneBy(['shipment' => $shipment]);
    }

    /** @return list<InPostShipment> */
    public function findByOrder(OrderInterface $order): array
    {
        $shipments = $order->getShipments()->toArray();
        if ([] === $shipments) {
            return [];
        }

        return $this->findBy(['shipment' => $shipments], ['id' => 'ASC']);
    }

    /**
     * Nadane, ale jeszcze bez numeru nadania albo w drodze — warto zapytać ShipX ponownie.
     *
     * @param list<string> $finalStatuses
     *
     * @return list<InPostShipment>
     */
    public function findAwaitingUpdate(array $finalStatuses, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.shipxId IS NOT NULL')
            ->orderBy('s.dispatchedAt', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $finalStatuses) {
            $qb->andWhere('s.status IS NULL OR s.status NOT IN (:final)')->setParameter('final', $finalStatuses);
        }

        /** @var list<InPostShipment> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
