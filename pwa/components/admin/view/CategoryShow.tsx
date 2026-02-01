import { ShowGuesser, FieldGuesser } from "@api-platform/admin";

export const CategoryShow = () => (
  <ShowGuesser>
    <FieldGuesser source="title" />
    <FieldGuesser source="createdAt" />
    <FieldGuesser source="updatedAt" />
  </ShowGuesser>
);
